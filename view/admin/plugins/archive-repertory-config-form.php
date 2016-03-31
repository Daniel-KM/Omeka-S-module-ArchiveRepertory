<?php
$form->prepare();
 echo $this->form()->openTag($form);
 ?>

<?php

    echo $this->translate('"Archive Repertory" plugin allows to save files in a hierarchical structure and to keep original name of files.') . '<br />';
    echo $this->translate('See %s for more information.', '<a href="https://github.com/Daniel-KM/ArchiveRepertory">ReadMe</a>') . '<br />';
    echo '<br />';
    echo $this->translate('When all options are set, files will be saved in "files / original / my_collection / item_identifier / original_filename.ext" instead of "files / original / hashed_filename.ext".') . '<br />';
    echo '<p><strong>' . $this->translate('Warning') . '</strong></p>';
    echo '<ul>';
    echo '<li>' . $this->translate('Currently, changes in these settings affect only new uploaded files. So, after a change, old files will continue to be stored and available as previously.') . '</li>';
    echo '<li>' . $this->translate('Nevertheless, when an item is updated, attached files will follow the current settings, so all files of a record will move and stay together inside the same folder.') . '</li>';
    echo '<li>' . $this->translate('Currently, no check is done on the name of folders, either for collections or for items. No files will be lost if two folders have the same name, but files attached to a record will be mixed in this folder.') . '</li>';
    echo '</ul>';
    echo $this->translate('Omeka works fine with filenames with Unicode characters ("é", "ñ", "Å"...). In case of issues, see %s.', '<a href="https://github.com/Daniel-KM/ArchiveRepertory">ReadMe</a>');
    echo ' ' . $this->translate('If this is not the case, filenames should use only Ascii characters.') . '<br />';
    if (empty($allow_unicode)) {
        echo '<p>' . $this->translate('This server is compatible with Unicode.') . '</p>';
    }
    else {
        echo '<p><strong>' . $this->translate('Warning') . '</strong></p>';
        echo $this->translate('This server is not fully compatible with Unicode:') . '<br />';
        echo '<ul>';
        if (isset($allow_unicode['ascii'])) {
            echo '<li>' . $allow_unicode['ascii'] . '</li>';
            echo ' ' . $this->translate('Use only an Ascii character as first character of your filenames or set the option "Convert first character of filename".') . '<br />';
        }
        if (isset($allow_unicode['cli'])) {
            echo '<li>' . $allow_unicode['cli'] . '</li>';
            echo $this->translate('Usually, this is not a problem with this plugin and common plugins.');
            echo ' ' . $this->translate('But if you use a plugin that calls a program via the command line of the server, filenames issues can occur.') . '<br />';
        }
        if (isset($allow_unicode['fs'])) {
            echo '<li>' . $allow_unicode['fs'] . '</li>';
            echo $this->translate('It is strongly recommanded to convert your filename to ascii.') . '<br />';
        }
        echo '</ul>';
    }
?>
<fieldset id="fieldset-collections"><legend><?php echo $this->translate('Item sets'); ?></legend>
    <div class="field">

                <div class="inputs">

      <?php echo $this->formRow($form->get('archive_repertory_collection_folder'));?>
        <p class="explanation">
<?php         $info = $this->translate('If you choose to add a folder, Omeka will add subfolders for each collection in "files" folders, for example "files/original/collection_identifier/".');
        $info .= ' ' . $this->translate('New files will be stored inside them. Old files will be moved when collection will be updated.') . '<br />';
        $info .= $this->translate("Note that if you choose a non unique name, files will be mixed in the same folder, with higher risk of name collision.");
        $info .= ' ' . $this->translate('So recommended ids are a specific metadata, "Dublin Core:Identifier", "Internal collection id" and eventually "Dublin Core:Title".') . '<br />';
        $info .= $this->translate('If this identifier does not exists, the Omeka internal collection id will be used.');
echo $info;
?>
</p>


</div>

                <div class="inputs">
<?php echo $this->formRow($form->get('archive_repertory_collection_prefix'));?>
                    <p class="explanation">
                        <?php echo $this->translate('Choose a prefix, for example "collection:", "record:" or "doc:", to select the appropriate metadata when they are multiple.');
                        echo ' ' . $this->translate('Let empty to use simply the first one.'); ?>
                    </p>
                </div>
            </div>

            <div id="collection-ascii" class="field">
<?php echo $this->formRow($form->get('archive_repertory_collection_convert'));
?>
                <div class="inputs">
                    <p class="explanation">
                        <?php echo $this->translate('Depending on your server and your needs, to avoid some potential issues, you can choose or not to rename every folder to its Ascii equivalent (or only the first letter).');
                        echo $this->translate('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.');
                        ?>
                    </p>

                </div>
            </div>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-items"><legend><?php echo $this->translate('Items'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
<?php echo $this->formRow($form->get('archive_repertory_item_folder')); ?>

                <p class="explanation">
                    <?php

        $info=$this->translate('If you choose to add a folder, Omeka will add subfolders for each item in "files" folders, for example "files/original/unique_identifier/');
        $info .= $this->translate('New files will be stored inside them. Old files will be moved when item will be updated.'). '<br />';;

        $info .= $this->translate("Note that if you choose a non unique name, files will be mixed in the same folder, with higher risk of name collision.");
        $info .= ' ' . $this->translate('So recommended ids are a specifc metadata, "Dublin Core Identifier", "Internal item id" and eventually "Dublin Core Title".');
        $info .= $this->translate('If this identifier does not exists, the Omeka internal item id will be used.');
echo $info;
?>
</p>
            </div>
            <div id="item-prefix" class="field">

    <div class="inputs">
<?php echo $this->formRow($form->get('archive_repertory_item_prefix')); ?>
    <p class="explanation">
                        <?php echo $this->translate('Choose a prefix, for example "item:", "record:" or "doc:", to select the appropriate metadata when they are multiple.');
                        echo ' ' . $this->translate('Let empty to use simply the first one.'); ?>
                    </p>

                </div>
            </div>
            <div id="item-ascii" class="field">

                <div class="inputs">
<?php echo $this->formRow($form->get('archive_repertory_item_convert')); ?>
                    <p class="explanation">
                        <?php echo $this->translate('Depending on your server and your needs, to avoid some potential issues, you can choose or not to rename every folder to its Ascii equivalent (or only the first letter).');
                        echo $this->translate('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.');
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</fieldset>
<fieldset id="fieldset-files"><legend><?php echo $this->translate('Files'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
<?php echo $this->formRow($form->get('archive_repertory_file_keep_original_name')); ?>


            <p class="explanation">
                <?php echo $this->translate('If checked, Omeka will keep original filenames of uploaded files and will not hash it.'); ?>
            </p>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
<?php echo $this->formRow($form->get('archive_repertory_file_convert'));
                 ?>
        </div>
        <div class="inputs five columns omega">
            <p class="explanation">
                <?php echo $this->translate('Depending on your server and your needs, to avoid some potential issues, you can choose or not to rename every file to its Ascii equivalent (or only the first letter).');
                echo $this->translate('In all cases, names are sanitized: "/", "\", "|" and other special characters are removed.');
                ?>
            </p>
        </div>
    </div>
    <div class="field">
        <div class="two columns alpha">
<?php echo $this->formRow($form->get('archive_repertory_file_base_original_name')); ?>
            <p class="explanation">

                <?php echo $this->translate('If checked, Omeka will keep only the base of original filenames in metadata, not their path or url. This option is independant from previous ones.') . '<br />'; ?>
            </p>
        </div>

</fieldset>
<fieldset id="fieldset-derivative-folders"><legend><?php echo $this->translate('Special derivative folders'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
<?php echo $this->formRow($form->get('archive_repertory_derivative_folders')); ?>

            <p class="explanation">
                <?php echo $this->translate('By default, Omeka support three derivative folders: "fullsize", "thumbnails" and "square_thumbnails".');
                echo ' ' . $this->translate('You can add other ones if needed (comma-separated values, like "special_thumbnails, circles").');
                echo ' ' . $this->translate('Folder names should be relative to the files dir "%s".', $local_storage);
                echo '<br />';
                echo ' ' . $this->translate('If a plugin does not use a standard derivative extension (for example ".jpg" for images), you should specified it just after the folder name, separated with a pipe "|", for example "zoom_tiles|_zdata, circles".');
                echo '<br />';
                echo ' ' . $this->translate('When this option is used, you should not change collection or item identifier and, at the same time, use a feature of the plugin that create derivative files.');
                echo ' ' . $this->translate('In that case, divide your process and change collection or identifier, save item, then use your plugin.')
                ?>
            </p>
        </div>

</fieldset>
<fieldset id="fieldset-move-process"><legend><?php echo $this->translate('Process'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
<?php echo $this->formRow($form->get('archive_repertory_move_process')); ?>

            <p class="explanation">
                <?php echo $this->translate('By default, the process uses the default internal functions of Omeka to process files.'); ?>
                <?php echo $this->translate('If needed, the standard functions of PHP can be used.'); ?>
            </p>
        </div>

</fieldset>
<fieldset id="fieldset-max-download"><legend><?php echo $this->translate('Maximum downloads by user'); ?></legend>
    <div class="field">
        <div class="two columns alpha">
<?php echo $this->formRow($form->get('archive_repertory_download_max_free_download')); ?>


            <p class="explanation">
                <?php echo $this->translate('Above this size, a captcha will be added to avoid too many downloads from a user.'); ?>
                <?php echo ' ' . $this->translate('Set a very high size to allow all files to be downloaded.'); ?>
                <?php echo ' ' . $this->translate('Note that the ".htaccess" and eventually "routes.ini" files should be updated too.'); ?>
            </p>

    </div>
    <div class='field'>
        <div class="two columns alpha">
        </div>
        <div class='inputs five columns omega'>
            <div class='input-block'>
<?php $this->ckEditor(); ?>
<?php  echo $this->formRow($form->get('archive_repertory_legal_text'));

 ?>
          <script type='text/javascript'>
                $('#archive_repertory_legal_text').ckeditor();
            </script>


                <p class="explanation">
                    <?php echo $this->translate('This text will be shown beside the legal checkbox to download a file.'); ?>
                    <?php echo ' ' . $this->translate("Let empty if you don't want to use a legal agreement."); ?>
                </p>

        </div>
    </div>
</fieldset>


<script type="text/javascript">
    var dropCollection = document.getElementById("archive_repertory_collection_folder");
    var fieldCollectionPrefix = document.getElementById("collection-prefix");
    var fieldCollectionAscii = document.getElementById("collection-ascii");
    dropCollection.onclick = function() {
        if (dropCollection.value == "none" || dropCollection.value == "id"){
            fieldCollectionPrefix.style.display = "none";
            fieldCollectionAscii.style.display = "none";
        } else {
            fieldCollectionPrefix.style.display = "block";
            fieldCollectionAscii.style.display = "block";
        }
    }

    var dropItem = document.getElementById("archive_repertory_item_folder");
    var fieldItemPrefix = document.getElementById("item-prefix");
    var fieldItemAscii = document.getElementById("item-ascii");
    dropItem.onclick = function() {
        if (dropItem.value == "none" || dropItem.value == "id"){
            fieldItemPrefix.style.display = "none";
            fieldItemAscii.style.display = "none";
        } else {
            fieldItemPrefix.style.display = "block";
            fieldItemAscii.style.display = "block";
        }
    }

    jQuery(document).ready(function () {
        dropCollection.onclick();
        dropItem.onclick();
    });
    jQuery(window).load(function () {
      Omeka.wysiwyg({
        mode: 'specific_textareas',
        editor_selector: 'html-editor'
      });
    });
</script>
