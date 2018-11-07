<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 10/29/2018
 * Time: 11:02 AM
 */
include_once('base.php');
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/css/select2.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.5/js/select2.min.js"></script>

<?php
$projectID = $_REQUEST['pid'];
$notifProjectID = $module->getProjectSetting("notif-project");
if ($projectID != "" && $notifProjectID != "") {
    if (!empty($_POST)) {
        //TODO Need to loop through notifications here, isn't just a single one
        $module->saveNotifSettings($projectID, $notifProjectID, $_POST);
    }
    $notifProject = new \Project($notifProjectID);
    $notifMetaData = $notifProject->metadata;
    $notifClassChoice = $module->getChoicesFromMetaData($notifMetaData[$module->getProjectSetting("notif-class")]['element_enum']);
    $existingNotifs = $module->getNotifications($notifProjectID);

    echo "<div class='col-md-12' style='padding:0;'>
        <form method='post' action='".$module->getUrl('notif_copy.php')."'>
            <div class='col-md-12 bg-info' style='padding:10px;'>
                <div class='col-md-10'>
                    Select a Notification for Duplication<br/>
                    <select id='notif_select' style='width:90%;text-overflow: ellipsis;'>";
                        foreach ($existingNotifs as $recordID => $eventData) {
                            foreach ($eventData as $event_id => $recordData) {
                                if ($event_id == "repeat_instances") continue;
                                echo "<option value='$recordID'>".$notifClassChoice[$recordData[$module->getProjectSetting('notif-class')]]." - ".$recordData[$module->getProjectSetting('notif-name')]." -- Project: ".$module->getProjectName($recordData[$module->getProjectSetting('project-field')])."</option>";
                            }
                        }
                    echo "</select>
                </div>
                <div class='col-md-2'>
                    <input type='button' value='Add' onclick='addNotif(\"notif_select\",\"notif_information\",\"".$projectID."\");'>
                </div>
            </div>
            <div class='col-md-12' id='notif_information' style='display:none;'>
            </div>
        </form>
    </div>";
    ?>
    <style>
        #notif_container {
            display: inline-block;
            min-height: 500px;
            text-align: center;
        }

        #notif_container > div {
            padding: 10px 0 10px 0;
            width: 100%;
        }

        #notif_information {
            min-height: 500px;
        }

        #accordion .ui-accordion-header {
            background-color: lightgreen;
        }
    </style>

    <script>
        function addNotif(notif,destination,projectid) {
            var notifValue = $('#' + notif).val();
            var notifCount = $('div[id^=notification_]').length;
            $.ajax({
                url: '<?=$module->getUrl('ajax_notifications.php')?>',
                method: 'post',
                data: {
                    'notif_record': notifValue,
                    'pid': projectid,
                    'repeatable_project': 'true',
                    'div_count': notifCount
                },
                success: function (data) {
                    //console.log(data);
                    $('#' + destination).css('display', 'inline-block').append(data);
                    $('#accordion > div').accordion({header: 'h3', collapsible: true, active: false});
                },
                error: function (errorThrown) {
                    console.log(errorThrown);
                }
            });
        }
    </script>
    <?php
}
    ?>