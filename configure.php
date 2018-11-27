<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 8/20/2018
 * Time: 2:59 PM
 */

include_once('base.php');
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/css/select2.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/js/select2.min.js"></script>

<?php
//TODO Add the POST variable to ajax call below that pulls from a dropdown of notif_class from notification project
$projectID = $_REQUEST['pid'];
$notifProjectID = $module->getProjectSetting("notif-project");
if ($projectID != "" && $notifProjectID != "") {
    /*echo "<pre>";
    print_r($existingNotifs);
    echo "</pre>";*/
    if (!empty($_POST)) {
        $module->saveNotifSettings($projectID,$notifProjectID,$_POST);
    }

    $notifProject = new \Project($notifProjectID);
    $notifMetaData = $notifProject->metadata;
    $notifClassChoice = $module->getChoicesFromMetaData($notifMetaData[$module->getProjectSetting("notif-class")]['element_enum']);
    $existingNotifs = $module->getNotifications($notifProjectID,$projectID);

    echo "<div class='col-md-12' style='padding:0;'>
        <form method='post' action='".$module->getUrl('configure.php')."'>
            <div id='notif_container' class='col-md-2 bg-info' style='padding:10px;'>
                <div style='font-weight:bold;border-bottom:4px solid;'>Select or Create a New Notification</div>
                <div>
                    Select a Notification<br/>
                    <select id='notif_select' onchange='hideShowNewNotif(this,\"new_role\");' style='width:80%;text-overflow: ellipsis;'></select>
                </div>
                <div id='new_role' style='display:none;'>
                    Name for New Notifcation<br/>
                    <input style='width:80%;' type='text' id='new_notif_name'/>
                </div>
                <div><button id='submit_role' type='button' onclick='loadNotif(\"notif_select\",\"new_notif_name\",\"notif_information\",\"".$projectID."\");'>Apply</button></div>
            </div>
            <div id='notif_information' style='display:none;width:100%;'>
            </div>
        </form>
    </div>";
 ?>
    <style>
        #notif_container {
            display:inline-block;
            min-height:500px;
            text-align:center;
        }

        #notif_container > div {
            padding: 10px 0 10px 0;
            width:100%;
        }

        #notif_information {
            min-height:500px;
        }
        #accordion .ui-accordion-header {
            background-color:lightgreen;
        }
    </style>

    <script>
        $(document).ready(function (){
            $('#notif_select').append($('<option></option>').attr('value','new').text('New Notification'));
            <?php
            foreach ($existingNotifs as $recordID => $eventData) {
                foreach ($eventData as $event_id => $recordData) {
                    if ($event_id == "repeat_instances") continue;
                    echo "$('#notif_select').append($('<option></option>').attr('value','".$recordID."').text('".$notifClassChoice[$recordData[$module->getProjectSetting('notif-class')]]." - ".$recordData[$module->getProjectSetting('notif-name')]."'));";
                }
            }
            ?>
            $('#notif_select').change();
        });
        function loadNotif(notif,newName,destination,projectid) {
            var notifValue = $('#'+notif).val();
            var nameValue = $('#'+newName).val();
            $.ajax({
                url: '<?=$module->getUrl('ajax_notifications.php')?>',
                method: 'post',
                data: {
                    'notif_record': notifValue,
                    'new_name': nameValue,
                    'pid': projectid,
                    'div_count': '1'
                },
                success: function (data) {
                    //console.log(data);
                    $('#'+destination).css('display','inline-block').html(data);
                    $('#accordion > div').accordion({ header: 'h3', collapsible: true, active: false });
                },
                error: function (errorThrown) {
                    console.log(errorThrown);
                }
            });
        }

        function hideShowNewNotif(select,target_id) {
            var selectValue = select.value;
            if (selectValue == 'new' || select.checked) {
                $('#'+target_id).show();
            }
            else {
                $('#'+target_id).hide().find('input').each(function() {
                    switch(this.type) {
                        case 'password':
                            jQuery(this).val('');
                            break;
                        case 'text':
                            jQuery(this).val('');
                            break;
                        case 'textarea':
                            jQuery(this).val('');
                            break;
                        case 'file':
                        case 'select-one':
                        case 'select-multiple':
                        case 'date':
                        case 'number':
                        case 'tel':
                        case 'email':
                            jQuery(this).val('');
                            break;
                        case 'checkbox':
                            this.checked = false;
                            break;
                        case 'radio':
                            this.checked = false;
                            break;
                    }
                });
            }
        }
    </script>
<?php
}