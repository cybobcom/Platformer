<?php


//
global $objPlatformUser;

//
$objMediaLibrary = CBinitObject("MediaLibrary");
/*
$objMediaLibrary = CBinitObject("MediaLibrary","0e4fb5c2-ec6d-11f0-adba-0a158978a168");

$arrSave = array();
$arrSave['name'] = "dev1";
$arrSave['title'] = "dev1";

$objMediaLibrary->saveContentUpdate("0e4fb5c2-ec6d-11f0-adba-0a158978a168",$arrSave);

$objMediaLibrary = CBinitObject("MediaLibrary","0e4fb5c2-ec6d-11f0-adba-0a158978a168");


echo "<pre>"; print_r($objMediaLibrary); echo "</pre>";	exit;
*/


//
$pathMedia = BASEDIR."data/media/";
if ( !file_exists($pathMedia) ) {
    if ( @mkdir($pathMedia, 0777, true) ) {
        @chmod($pathMedia,0777);
    }

}

//
if ( !isset($_REQUEST["identifier"]) || $_REQUEST["identifier"] == "" ) {
    $_SESSION["medialibrary_identifier"] = "";
}


?>
<style>
    body {
        margin-bottom: 0px;
        padding-bottom: 0px;
    }

    #drop-area {
        border:0px solid #000;
        padding: 0px;
        margin-bottom: -100px;
        padding-bottom: 100px;

    }
</style>

<script>


    function listItems(){

        var strSearch = $('#filter_search').val();

        var url = "<?php echo BASEURL; ?>views/medialibrary/listItems/";
        url += "&search="+encodeURI(strSearch);

        $( "#container_content" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#container_content" ).load( url, function() {
            //alert( "Load was performed. "+url );
        });

    }

    function newItem(){

        globalDetailModal.show();

        var url = "<?php echo BASEURL; ?>views/medialibrary/newItem/";

        $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#detailModalContent" ).load( url, function() {
            //alert( "Load was performed." );
        });

    }

    function editItem(id){

        globalDetailModal.show();

        var url = "<?php echo BASEURL; ?>views/medialibrary/editItem/?uid="+id;

        $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#detailModalContent" ).load( url, function() {
            //alert( "Load was performed." );
        });

    }

    function deleteItem(id){

        var r = confirm("Eintrag wirklich löschen!");
        if (r == true) {

            globalDetailModal.show();

            var url = "<?php echo BASEURL; ?>/controller/medialibrary/deleteItem/?uid="+id;

            $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
            $( "#detailModalContent" ).load( url, function() {
                //alert( "Load was performed." );
            });

        }

    }

    function deleteDirectory(uid){

        var r = confirm("Verzeichnis und enthaltene Dateien wirklich unwiederbringlich löschen?");
        if (r == true) {


            globalDetailModal.show();

            var url = "<?php echo BASEURL; ?>/controller/medialibrary/deleteDirectory/";
            url += "&uid="+encodeURI(uid);
            //alert(url);

            $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
            $( "#detailModalContent" ).load( url, function(result) {

                globalDetailModal.toggle();

                listItems();
            });

        }

    }

    function newDirectory(){

        globalDetailModal.show();

        var url = "<?php echo BASEURL; ?>views/medialibrary/newDirectory";

        $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#detailModalContent" ).load( url, function() {
            //alert( "Load was performed." );
        });

    }

    function editDirectory(id){

        globalDetailModal.show();

        var url = "<?php echo BASEURL; ?>views/medialibrary/editDirectory";
        url += "&uid="+encodeURI(id);

        $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#detailModalContent" ).load( url, function() {
            //alert( "Load was performed." );
        });

    }

    function gotoDirectory(dir){

        //alert(dir);

        var url = "<?php echo BASEURL; ?>/controller/medialibrary/gotoDirectory/";
        url += "&dir="+encodeURI(dir);

        $( "#container_content" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#container_content" ).load( url, function() {
            //alert( "Load was performed." );
            listItems();
        });

    }

    function generateThumb(url_to_file,medialibrary_uid){

        var url = "<?php echo BASEURL; ?>/controller/medialibrary/generateThumb/";
        url += "&url_to_file="+url_to_file;
        url += "&medialibrary_uid="+medialibrary_uid;

        $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#detailModalContent" ).load( url, function() {
            //alert( "Load was performed." );

        });

    }

    function showFile(file){

        globalBigModal.show();

        // iframe
        var url = "<?php echo BASEURL; ?>views/medialibrary/previewItem/?file="+encodeURI(file);

        $( "#content_big" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#content_big" ).load( url, function() {
            //alert( "Load was performed." );

        });

        $( "#content_big" ).height( $(window).height() - 200);
        $( "#content_big" ).css("z-index","1000000");

    }

    function showPrivateFile(item){
        //alert(item);

        globalBigModal.show();

        // iframe
        $( "#content_big" ).html('<iframe id="iframe" style="border:0px solid #666CCC; " title="item" src="<?php echo BASEURL; ?>ajax/&am=Document.showItem&item='+item+'" frameborder="10" scrolling="auto" height="100%" width="100%" onDEVload="this.height=this.contentWindow.document.body.scrollHeight;"></iframe>');

        $( "#content_big" ).height( $(window).height() - 200);

    }

    function downloadPrivateFile(item){

        var url = '<?php echo BASEURL; ?>views/medialibrary/downloadItem/?item='+item;
        location.href = url;

    }

    function toggleGridList(){

        /*
        var url = "<?php echo BASEURL; ?>/controller/address/updateItem/";
        url += "&field=data_toggle_gridlist";
        url += "&options=grid,list";

        $( "#detailModalContent" ).html('<div class="mx-auto p-2 text-center ajax_loader"></div>');
        $( "#detailModalContent" ).load( url, function() {
            //alert( "Load was performed." );
            listItems();
        });
        */

        //var value = $('#selector_toggle_gridlist').attr("data-toggle-value");


        //
        var tmp = {};

        //
        var arrSave = {};
        arrSave["settings_medialibrary_toggle_gridlist"] = "grid,list";

        //
        tmp["toggle"] = arrSave;
        //tmp["toggle_options"] = "grid,list";

        //
        var url = "<?php echo BASEURL; ?>controller/address/updateItem/";
        url += "?id=<?php echo $objPlatformUser->getAttribute("address_uid"); ?>";
        //alert(url);

        $.ajax({
            'url': url,
            'type': 'POST',
            'data': tmp,
            'success': function(result){

                //
                listItems();
            }
        });

    }



</script>



<div id="drop-area" class="target">
    <div class="container-fluid g-3 plattform_content">
        <div class="row ">
            <div class="col-12 ">

                <div style="" class="container_filter_fixed">

                    <div align="right" style=""><a onclick="newDirectory();" class="cb_pointer"><i class="bi bi-folder icon-button-primary"></i></a></div>


                    <div class="upload-btn-wrapper" style="float: right;">
                        <label for="uploadfiles">
                            <div class="bi bi-plus-lg icon-button-primary"></div>
                            <input type="file" id="uploadfiles" class="inputfile" data-multiple-caption="{count} files selected" multiple="multiple" onDEVchange="handleFiles(this.files)" style="display: none;" />
                        </label>
                        <span id="uploadfiles_amount" style="font-size:15px;padding-left: 4px;"></span>
                    </div>

                    <div id="id_submit_galleryandprogress" style="display: none;">
                        <!-- TODO: add progress bar and gallery -->
                        <progress id="progress-bar" max=100 value=0 style="width: 100%;"></progress>
                        <div id="gallery" /></div>
                </div>







                <div align="left" style="float:right;margin-top:8px;margin-right:210px;">
                    <div style=" position: absolute; width: 200px;">
                        <span style="flo2at:right; position:absolute; right:10px; top:8px; visibility:hidden; color: gray; font-size: 12px;" class="id_search_reset bi bi-x-lg"></span>
                        <input name="search" type="text" class="form-control form-control-sm" value="" placeholder="Suchen" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"  id="filter_search" />
                    </div>
                </div>
                <script>
                    $('#filter_search').off('input keyDEVup paste');
                    $('#filter_search').on('input keyDEVup paste', function(){

                        $('.id_search_reset').css('visibility', 'visible');

                        var str = $('#filter_search').val();
                        if ( str.length >= 3 ) {
                            listItems();
                        }

                    });
                    $(document).off('click touchstart tap', '.id_search_reset');
                    $(document).on('click touchstart tap', '.id_search_reset', function () {

                        $('#filter_search').val("");
                        $('.id_search_reset').css('visibility', 'hidden');
                        listItems();

                    });
                </script>







                <div id="selector_toggle_gridlist"  align="left" style="float:right;margin-top:10px;margin-right:20px;  border:1px solid #D3D3D3; background-color: white; border-radius: 4px;">
                    <div class="bi bi-grid button_toggle classid_toggle_gridlist_grid" style="padding:5px"> </div><div class="bi bi-list button_toggle classid_toggle_gridlist_list" style="padding:5px"> </div>
                </div>
                <script>
                    $(document).off('click', '#selector_toggle_gridlist');
                    $(document).on('click', '#selector_toggle_gridlist', function () {
                        toggleGridList();
                    });
                </script>


                <?php
                //
                //
                //
                if ( $objPlatformUser->getAttribute("status") == "superadmin" || $objPlatformUser->getAttribute("status") == "admin" ) {
                    ?>

                    <div align="right" ><a onclick="analyzeStatus();" class="cb_pointer" ><i class="bi bi-pie-chart icon-button-primary" style="margin-right: 16px !important;""></i></a></div>

                    <?php
                }
                ?>



                <h1>Medien</h1>


            </div>


            <div id="container_content" style="margin-top:20px;">
            </div>

        </div>
    </div>
</div>
</div>

<script>
    listItems();
</script>




<script>

    var uploadProgress = []
    var progressBar = document.getElementById('progress-bar')

    var arrFiles4Upload = [];// = new FormData();
    var arrFiles4UploadNames = [];

    $(document).off('change', '.inputfile');
    $(document).on('change', '.inputfile', function (e) {

        //alert("dev");

        var that = this;
        setTimeout(function(){
            handleFiles(that.files);
        }, 100);

    });



    function handleFiles(files) {
        console.log(files);

        for (var i = 0, l = files.length; i < l; i++) {
            // 		    arrFiles4Upload.append("file-" + [...arrFiles4Upload.keys()].length, files[i], files[i].name);
            console.log(files[i].name);

            // avoid drag & drop with files which are already there
            if ( files[i].name == "image.png" ) continue;

            if ( ! mycontains(arrFiles4UploadNames, files[i].name)) {
                arrFiles4Upload.push(files[i]);
                arrFiles4UploadNames.push(files[i].name);
            }

        }

        //
        if ( arrFiles4Upload.length >= 1 ) {


            //
            // 		$( '#gallery' ).html("");

            $( '#uploadfiles_amount' ).html( arrFiles4Upload.length );

            $( '#uploadfiles' ).val('');

            $("#id_submit_galleryandprogress").css("display", "block");

            /*
              files = [...files]
              initializeProgress(files.length)
            // 	  files.forEach(uploadFile)
              files.forEach(previewFile)
            */

            arrFiles4Upload = [...arrFiles4Upload]
            initializeProgress(arrFiles4Upload.length)
            // 	  arrFiles4Upload.forEach(uploadFile)
            //arrFiles4Upload.forEach(previewFile)


            console.log(arrFiles4Upload);
            console.log(arrFiles4UploadNames);

            // finaly
            // 		$( '#uploadfiles_amount' ).html( arrFiles4Upload.length );

            for(var i=0; i<arrFiles4Upload.length; i++){
                uploadFile(arrFiles4Upload[i],i);
            }

        }

    }

    function mycontains(a, obj) {
        for (var i = 0; i < a.length; i++) {
            if (a[i] === obj) {
                return true;
            }
        }
        return false;
    }

    function initializeProgress(numFiles) {
        progressBar.value = 0
        uploadProgress = []

        for(var i = numFiles; i > 0; i--) {
            uploadProgress.push(0)
        }
    }

    function updateProgress(fileNumber, percent) {
        uploadProgress[fileNumber] = percent
        /*
              var total = uploadProgress.reduce((tot, curr) => tot + curr, 0) / uploadProgress.length
              console.debug('update', fileNumber, percent, total)
              progressBar.value = total
        */
        var total = 0;
        for (var i = 0, len = uploadProgress.length; i < len; i++) {
            total += uploadProgress[i];
        }
        progressBar.value = total;
    }

    function uploadFile(file, i) {

        //
        var current_dir = $('.id_current_path').val();
        console.log(current_dir);

        //
        var length = arrFiles4Upload.length;

        //
        updateProgress(i, 0) // <- Add this

        //
        var url = "<?php echo BASEURL; ?>/controller/medialibrary/uploadFile/";
        url += "&target="+encodeURI(current_dir);
        console.log(url);

        //
        var strError = "";

        var xhr = new XMLHttpRequest();
        //xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded; charset=ISO-8859-1')

        var fd = new FormData();
        xhr.open("POST", url, true);
        xhr.onreadystatechange = function() {

            if (xhr.readyState == 4 && xhr.status == 200) {
                // Every thing ok, file uploaded
                console.log(xhr.responseText); // handle response.

                if ( xhr.responseText == "error" ) {
                    strError = "Ungültige Datei.";
                }

                updateProgress(i, 100) // <- Add this

                //             $("#upload_message").html("");

                //alert(xhr.responseText);
                //             addToDocuments(xhr.responseText);


                // reload
                var c = i + 1;
                if ( c == length ) {

                    //location.reload();

                    // clear file upload to make new upload possible
                    $("#uploadfiles").val("");
                    $( '#uploadfiles_amount' ).html("");

                    $( '#gallery' ).html("");
// 			        $( '#drop-area' ).css("border-color","#ccc");


// 					$("#id_submit").css("display", "block");
// 					$("#id_submit_loader").css("display", "none");
                    $("#id_submit_galleryandprogress").css("display", "none");


                    /*
                                        setTimeout(function() {
                                            var url = "###LINK:19###&am=ProjectTodoList.showDiscussionList&identifier="+identifier;
                                            $( "#discussion_container" ).load( url, function() {
                                                //alert( "Load was performed. "+url );


                                                updateProgress(i, 0) // <- Add this
                                                $("#id_submit_galleryandprogress").css("display", "none");

                                            });
                                        }, 1000);
                    */


                    arrFiles4Upload = [];// = new FormData();
                    arrFiles4UploadNames = [];

                    updateProgress(i, 0) // <- Add this


                    listItems();

                    //
                    if ( strError != "" ) {
                        alert(strError);
                    }

                }


            }
        };

        xhr.upload.addEventListener("progress", function(e) {
            updateProgress(i, (e.loaded * 100.0 / e.total) || 100)
        })


        /*
          // Update progress (can be used to show progress indicator)
      xhr.upload.addEventListener("progress", function(e) {
        updateProgress(i, (e.loaded * 100.0 / e.total) || 100)
      })

      xhr.addEventListener('readystatechange', function(e) {
        if (xhr.readyState == 4 && xhr.status == 200) {
          updateProgress(i, 100) // <- Add this
        }
        else if (xhr.readyState == 4 && xhr.status != 200) {
          // Error. Inform the user
        }
      })
      */



        fd.append('uploaded_file', file);
        xhr.send(fd);


    }



    //
    // https://www.smashingmagazine.com/2018/01/drag-drop-file-uploader-vanilla-js/
    // https://codepen.io/joezimjs/pen/yPWQbd
    //
    // ************************ Drag and drop ***************** //

    // remove old eventlistener
    // 2025-03-17 bob : may crash
    //$('#drop-area').replaceWith($('#drop-area').clone());


    var dropArea = document.getElementById("drop-area");

    // Prevent default drag behaviors
    /*
        ;['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
          dropArea.addEventListener(eventName, preventDefaults, false)
          document.body.addEventListener(eventName, preventDefaults, false)
        })
    */
    var arr1 = ['dragenter', 'dragover', 'dragleave', 'drop'];
    for (var i = 0, len = arr1.length; i < len; i++) {
        dropArea.addEventListener(arr1[i], preventDefaults, false);
        //document.body.addEventListener(arr1[i], preventDefaults, false);
    }


    // Highlight drop area when item is dragged over it
    /*
        ;['dragenter', 'dragover'].forEach(eventName => {
          dropArea.addEventListener(eventName, highlight, false)
        })
    */
    var arr2 = ['dragenter', 'dragover'];
    for (var i = 0, len = arr2.length; i < len; i++) {
        dropArea.addEventListener(arr2[i], highlight, false);
    }


    /*
        arr3.forEach(eventName => {
          dropArea.addEventListener(eventName, unhighlight, false)
        })
    */
    var arr3 = ['dragleave', 'drop'];
    for (var i = 0, len = arr3.length; i < len; i++) {
        dropArea.addEventListener(arr3[i], unhighlight, false);
    }

    // Handle dropped files
    dropArea.addEventListener('drop', handleDrop, false);

    function preventDefaults (e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight(e) {
        dropArea.classList.add('highlight');
    }

    function unhighlight(e) {
        dropArea.classList.remove('active');
    }

    function handleDrop(e) {
        var dt = e.dataTransfer;
        var files = dt.files;

        handleFiles(files);
    }





</script>








