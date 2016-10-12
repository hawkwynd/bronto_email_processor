<?php
/**
 * Date: 7/13/16
 *
 * This application imports the XML file from the provider's URL and
 * prepares a datagrab XML file which is imported into the EE 'search products from store' channel.
 *
 * It automatically downloads the image from the product page, and if it is not in our images directory, saves
 * a copy of it there for use in the search results. A thumbnail is also stored of the original image at 240x240 pixels.
 *
 *
 */

ini_set('max_execution_time', 100);


/* Image handlers */
$pathinfo                   = pathinfo( $_SERVER['DOCUMENT_ROOT'] );
$dirname                    = $pathinfo['dirname'].'/'. $pathinfo['basename'];
$img_source                 = "$dirname/images/products/";
$img_dest                   = "$dirname/images/products/thumbs/";
$supported_ext              = array('JPG','jpg','GIF','gif','PNG','png');
$feed_url                   = "http://www.example.com/feed.xml";
$x                          = 0;
$removedNodes               = array();

// Grab the feed
$feedXml                    = @file_get_contents($feed_url);

// Make sure we have something before continuing, else die!!!
if($feedXml){

// Fix bad coding in XML file
    $feedXml    = str_replace('<offers>','<Offers>', $feedXml);
    file_put_contents("xml/products_xml_raw.xml", $feedXml);
    echo "Download of $feed_url complete. <br/>";
    $filexml    ='xml/products_xml_raw.xml';

}else{
    die('Could not obtain ' . $feed_url);
}


// Load our XML Dom and lets rock this.

$xml                        = new DOMDocument('1.0','utf-8');
$xml->formatOutput          = true;
$xml->preserveWhiteSpace    = false;
$xml->validateOnParse       = true;
$xml->load($filexml); // load up the XML data

# Get the Offer(s) nodes, and count them.
$offer              = $xml->getElementsByTagName('Offer');
$counter            = $xml->getElementsByTagName('Offer')->length;

echo "<h2>Processing " . $counter . " job records in XML feed</h2>";

$unwanted = array(
    '%config_load file="[^"]+"%i',
    '%scope="[^"]+"%i',
    '%include file="[^"]+"%i'
);

// Loop through the jobs, and fix stuff, and make stuff more
// human readable and nice and tidy for datagrabbing it into EE.

foreach($offer as $key => $values){

    flush();
    ob_flush();

    // Create a status element default is open
    $status = $xml->createElement("status");
    $status->nodeValue = "Open";

    # Price values
    foreach( $values->getElementsByTagName('CurrentPrice') as $price){
        echo "Price: " . $price->nodeValue . "<br/>";
    }

    // Category of the product
    foreach($values->getElementsByTagName('MerchantCategory') as $category){

        # Test for empty category value or 0 priced item
        if(empty($category->nodeValue) || $price->nodeValue == '0.00'){

            echo "Category: Empty<br/>";
            # Empty category or no price, set status to closed
            $removedNodes[]     = $values;
            $status->nodeValue  = 'Closed';

        }else{
            echo "Category: " . $category->nodeValue . "<br/>";
        }
    }

    // Title of Product - Clean it up for XML digestion
    foreach($values->getElementsByTagName('OfferName') as $title){
        $titleCDATA = $xml->createCDATASection(
            strip_tags(
                preg_replace($unwanted, '',
                    html_entity_decode( $title->nodeValue )
                )
            )
        );

        $title->replaceChild($titleCDATA, $title->childNodes->item(0));
       echo "OfferName: ". $title->nodeValue . "<br/>";
    }


    //ImageURL processing -- This is some of my more ingenius work..
    foreach($values->getElementsByTagName('ReferenceImageURL') as $imageURL){

        $imageArray = pathinfo($imageURL->nodeValue);
        $imageFile  = basename($imageURL->nodeValue);

        # make sure our values are clean, before trying to download
        # something we don't know is an image, or foolish URL

        if(  in_array($imageArray['extension'], $supported_ext ) ) {

            # Check if the file exists on our side, if not download it
            # and save it in the /images/uploads directory

                if(! is_file('images/products/' . $imageFile)){

                    # download the image, or attempt to anyways, and we'll
                    # deal the dance with the devil when we get there.

                    $image = @file_get_contents($imageURL->nodeValue);

                    if($image){

                        # save the original image in the images/products directory
                        @file_put_contents('images/products/' . $imageFile, $image);

                        # Create a thumbnail of our image we just downloaded.
                        $thumb              = new Imagick('images/products/'.$imageFile);
                        $thumb->setcompression(Imagick::COMPRESSION_JPEG);
                        $thumb->setcompressionquality(95);
                        $thumb->resizeimage(240,240,imagick::FILTER_LANCZOS, 1);
                        $thumb->writeimage('images/products/thumbs/'.$imageFile);

                        # Set the image path to the website for display
                        $imageURL->nodeValue ='http://'. $_SERVER['HTTP_HOST'] . '/images/products/' . $imageFile;
                        echo " ImageURL: <a href='" . $imageURL->nodeValue . "' target='_blank'>" .$imageURL->nodeValue . "</a><br/>";

                        $thumbURL = 'http://'. $_SERVER['HTTP_HOST'] . '/images/products/thumbs/' . $imageFile;
                        $refThumb             = $xml->createElement('referenceimagethumb', $thumbURL);
                        $values->appendChild($refThumb);
                        echo " ThumbURL: <a href='" . $refThumb->nodeValue . "' target='_blank'>" .$refThumb->nodeValue . "</a><br/>";

                    }

                }else{
                    // we already have a copy of the image, just update the paths to the images to our localhost.
                    echo "<h3>". $imageFile . " already exists in /images/products directory.</h3>";

                    # Set the image path to the website for display
                    $imageURL->nodeValue ='http://'. $_SERVER['HTTP_HOST'] . '/images/products/' . $imageFile;
                    echo " ImageURL: <a href='" . $imageURL->nodeValue . "' target='_blank'>" .$imageURL->nodeValue . "</a><br/>";

                    $thumbURL = 'http://'. $_SERVER['HTTP_HOST'] . '/images/products/thumbs/' . $imageFile;
                    $refThumb             = $xml->createElement('referenceimagethumb', $thumbURL);
                    $values->appendChild($refThumb);
                    echo " ThumbURL: <a href='" . $refThumb->nodeValue . "' target='_blank'>" .$refThumb->nodeValue . "</a><br/>";
                }

        }else{ // Don't puke, just alert me the image is not valid and remove it from our nodes.

            echo "<h3>Invalid IMAGE: " . $imageFile . "</h3>";
            $imageURL->nodeValue = ''; // clear element values
            $values->appendChild( $xml->createElement('referenceimagethumb', $imageURL->nodeValue ) ); // create empty element

        } // if in_array()
    } // foreach()

    // ActionURL
    foreach($values->getElementsByTagName('ActionURL') as $link){
        echo "Store Link: ";
        echo $link->nodeValue . "<br/>";
    }

    // OfferDescription
    foreach($values->getElementsByTagName('OfferDescription') as $OfferDescription){

          #Update our description with clean Non-HTML text. Just the text, ma'am..
        if( strlen($OfferDescription->nodeValue) > 0 ){
                $DescriptionCDATA = $xml->createCDATASection(
                  strip_tags(
                      preg_replace($unwanted, '',
                      html_entity_decode( $OfferDescription->nodeValue )
                     )
                  )
              );

              $OfferDescription->replaceChild($DescriptionCDATA, $OfferDescription->childNodes->item(0));

        }

        echo "Description:" . $OfferDescription->nodeValue . "<br/>";
    }


    # Process stock status
    foreach($values->getElementsByTagName('InStock') as $inStock){
        echo "InStock: ";
       echo $inStock->nodeValue == 1 ? "TRUE" : "FALSE";
       echo "<br/>";
    }


    #Set the status
    $values->appendChild($status);
    echo "Status: " . $status->nodeValue . "<br/>";

    echo "<hr>";

} // foreach offer



// Stuff the file in the datagrab directory, where ExpressionEngine Datagrab module will import.
htmlentities($xml->save('xml/products_xml_processed.xml'));

exit();
