<?php
if ( isset($_REQUEST['id']) && $_REQUEST['id'] != "" ) {

    $objMCP = CBinitObject("MCP");
    //CBLog($objMCP);

    echo $objMCP->generateToken($_REQUEST['id']);

}