<?php

$path = "assets/";

$arrFiles = array_diff(scandir($path), array('.', '..'));
//echo "<pre>"; print_r($arrFiles); echo "</pre>";

$strCSS = "";
$strHTML = "";
if ( is_array($arrFiles) && count($arrFiles) >= 1 ) {
	foreach ( $arrFiles as $r=>$v ) {
		
		$n = $v;
		$n = str_replace(".svg", "", $n);
		
		$strCSS .= '
		
.bi-'.$n.'::before {
  display: inline-block;
  content: "-";
  background-image: url(assets/'.$v.');
  background-repeat: no-repeat;
  background-size: 1rem 1rem;
}
		
		';

	
		$strHTML .= '
		
		
		<span class="bi-'.$n.'">bi-'.$n.'</span><br>
		
		';
	
	
	}
}

//
// css
//
//echo $strCSS;
file_put_contents("bi.css", $strCSS);

//
// html
//
echo '<style>';
echo $strCSS;
echo '</style>';

echo $strHTML;