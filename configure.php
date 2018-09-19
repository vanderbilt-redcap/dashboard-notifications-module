<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 8/20/2018
 * Time: 2:59 PM
 */
include_once('base.php');
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$projectID = $_GET['pid'];
$notifProjectID = $module->getProjectSetting("notif-project");
if ($projectID != "" && $notifProjectID != "") {
    $project = new \Project($projectID);
    $notifProject = new \Project($notifProjectID);
    /*echo "<pre>";
    print_r($existingNotifs);
    echo "</pre>";*/
    if (!empty($_POST)) {
        /*echo "<pre>";
        print_r($_POST);
        echo "</pre>";*/
        $overwrite = "overwrite";
        $recordID = "";
        if ($_POST['record_id'] != "") {
            $recordID = db_real_escape_string($_POST[$notifProject->table_pk]);
        }
        else {
            $recordID = $module->getAutoID($notifProjectID,$notifProject->firstEventId);
        }
        if ($recordID != "") {
            $saveData[$notifProject->table_pk] = $recordID;
            $notifType = db_real_escape_string($_POST[$module->getProjectSetting("notif-type")]);
            $saveData[$module->getProjectSetting("project-field")] = $projectID;
            $saveData[$module->getProjectSetting("notif-name")] = db_real_escape_string($_POST[$module->getProjectSetting("notif-name")]);
            $saveData[$module->getProjectSetting("notif-alert")] = db_real_escape_string($_POST[$module->getProjectSetting("notif-alert")]);
            $saveData[$module->getProjectSetting("notif-class")] = db_real_escape_string($_POST[$module->getProjectSetting("notif-class")]);
            $saveData[$module->getProjectSetting("notif-type")] = $notifType;
            $saveData[$module->getProjectSetting("notif-active")] = db_real_escape_string($_POST[$module->getProjectSetting("notif-active")]);
            $saveData[$module->getProjectSetting("role-list")] = db_real_escape_string(implode(",",$_POST[$module->getProjectSetting("role-list")]));
            $saveData[$module->getProjectSetting("role-resolve")] = db_real_escape_string(implode(",",$_POST[$module->getProjectSetting("role-resolve")]));
            $notifSettings = array();
            switch ($notifType) {
                case "0":
                    break;
                case "1":
                    $notifSettings['project_production'] = db_real_escape_string($_POST['project_production']);
                    break;
                case "2":
                    foreach ($_POST['form_names'] as $index => $formName) {
                        $notifSettings['forms_field'][$index] = db_real_escape_string($formName);
                    }
                    $notifSettings['project_production'] = db_real_escape_string($_POST['project_production']);
                    break;
                case "3":
                    foreach ($_POST['field_names'] as $index => $fieldName) {
                        $notifSettings['field_names'][$index] = db_real_escape_string($fieldName);
                    }
                    break;
                case "4":
                    $notifSettings['user_new'] = db_real_escape_string($_POST['user_new']);
                    $notifSettings['user_edit'] = db_real_escape_string($_POST['user_edit']);
                    break;
                case "5":
                    foreach ($_POST['field_names'] as $index => $fieldName) {
                        $notifSettings['field_names'][$index] = db_real_escape_string($fieldName);
                        $notifSettings['field_value'][$index] = array_map('db_real_escape_string', (is_array($_POST['field_value_'.$index]) ? $_POST['field_value_'.$index] : array($_POST['field_value_'.$index])));
                    }
                    break;
                case "6":
                    foreach ($_POST['field_names'] as $index => $fieldName) {
                        $notifSettings['field_names'][$index] = db_real_escape_string($fieldName);
                        $notifSettings['field_value'][$index] = array_map('db_real_escape_string', (is_array($_POST['field_value_'.$index]) ? $_POST['field_value_'.$index] : array($_POST['field_value_'.$index])));
                    }
                    break;
                case "7":
                    foreach ($_POST['field_names'] as $index => $fieldName) {
                        $notifSettings['field_names'][$index] = db_real_escape_string($fieldName);
                        $notifSettings['field_value'][$index] = array_map('db_real_escape_string', (is_array($_POST['field_value_'.$index]) ? $_POST['field_value_'.$index] : array($_POST['field_value_'.$index])));
                    }
                    $notifSettings['record_count'] = db_real_escape_string($_POST['record_count']);
                    break;
            }
            $notifSettings['past_due'] = db_real_escape_string($_POST['notif_pastdue']);
            $saveData[$module->getProjectSetting("access-json")] = json_encode($notifSettings);

            \Records::saveData($notifProjectID, 'array', [$saveData[$notifProject->table_pk] => [$notifProject->firstEventId => $saveData]],$overwrite);
        }
    }
    $existingNotifs = $module->getNotifications($notifProjectID);
    echo "<div class='col-md-12'>
        <form method='post' action='".$module->getUrl('configure.php')."'>
            <div id='notif_container' class='col-md-2 bg-info' style='min-width:150px;padding:10px;'>
                <div style='font-weight:bold;border-bottom:4px solid;'>Select or Create a New Notification</div>
                <div>
                    Select a Notification<br/>
                    <select id='notif_select' onchange='hideShowNewNotif(this,\"new_role\");' style='min-width:125px;text-overflow: ellipsis;'></select>
                </div>
                <div id='new_role' style='display:none;'>
                    Name for New Notifcation<br/>
                    <input type='text' id='new_notif_name'/>
                </div>
                <div><button id='submit_role' type='button' onclick='loadRole(\"notif_select\",\"new_notif_name\",\"notif_information\");'>Apply</button></div>
            </div>
            <div class='col-md-10' id='notif_information' style='display:none;'>
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
            <?php
            foreach ($existingNotifs as $recordID => $eventData) {
                foreach ($eventData as $event_id => $recordData) {
                    if ($event_id == "repeat_instances") continue;
                    echo "$('#notif_select').append($('<option></option>').attr('value','".$recordID."').text('".$recordData[$module->getProjectSetting('notif-name')]."'));";
                }
            }
            ?>
            $('#notif_select').append($('<option></option>').attr('value','new').text('New Notification'));
        });
        function loadRole(notif,newName,destination) {
            var notifValue = $('#'+notif).val();
            var nameValue = $('#'+newName).val();
            $.ajax({
                url: '<?=$module->getUrl('ajax_notifications.php')?>',
                method: 'post',
                data: {
                    'notif_record': notifValue,
                    'new_name': nameValue
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
            if (selectValue == 'new') {
                $('#'+target_id).show();
            }
            else {
                $('#'+target_id).hide().find('input').val('');
            }
        }
    </script>
<?php
}