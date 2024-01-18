<?php
/**
 * Created by PhpStorm.
 * User: moorejr5
 * Date: 8/30/2018
 * Time: 1:38 PM
 */
$projectID = $_GET['pid'];
$notifProjectID = $module->getProjectSetting("notif-project");
$returnHTML = "";

if (isset($_POST['field_name']) && is_numeric($_POST['field_count']) && is_numeric($projectID) && is_numeric($notifProjectID)) {
    $fieldName = db_real_escape_string($_POST['field_name']);
    $fieldCount = db_real_escape_string($_POST['field_count']);
    $recordID = db_real_escape_string($_POST['record_id']);
    $project = new \Project($projectID);
    $notifProject = new \Project($notifProjectID);
    $recordData = \Records::getData($notifProjectID, 'array', array($recordID));
    $notifSettings = json_decode($recordData[$recordID][$notifProject->firstEventId][$module->getProjectSetting("access-json")],true);

    $metaData = $project->metadata;
    $columnCount = 1;
    $choiceCount = 0;
    if (in_array($metaData[$fieldName]['element_type'],array("radio","select","checkbox"))) {
        $fieldChoices = $module->getChoicesFromMetaData($metaData[$fieldName]['element_enum']);
        $returnHTML = "<table><tr>";
        foreach ($fieldChoices as $raw => $label) {
            $raw = htmlspecialchars($raw, ENT_QUOTES);
            $label = htmlspecialchars($label, ENT_QUOTES);
            $returnHTML .= "<td><span><input type='checkbox' id='".FIELD_VALUE_SETTING."_".$fieldCount."_".$choiceCount."' name='".FIELD_VALUE_SETTING."_".$fieldCount."[]' value='$raw' /></span><span>$label</span></td>";
            if ($columnCount % 3 == 0) {
                $returnHTML .= "</tr><tr>";
                $columnCount = 0;
            }
            $columnCount++;
            $choiceCount++;
        }
        $returnHTML .= "</tr></table>";
    }
    else if (in_array($metaData[$fieldName]['element_type'],array("yesno","truefalse"))) {
        if ($metaData[$fieldName]['element_type'] == "truefalse") {
            $oneLabel = "True";
            $zeroLabel = "False";
        }
        else {
            $oneLabel = "Yes";
            $zeroLabel = "No";
        }
        $returnHTML = "<table>
            <tr>
                <td>
                <span><input type='radio' id='".FIELD_VALUE_SETTING."_".$fieldCount."_0' name='".FIELD_VALUE_SETTING."_".$fieldCount."[]' value='0' /></span><span>$zeroLabel</span>
                </td>
            </tr>
            <tr>
                <td>
                <span><input type='radio' id='".FIELD_VALUE_SETTING."_".$fieldCount."_1' name='".FIELD_VALUE_SETTING."_".$fieldCount."[]' value='1' /></span><span>$oneLabel</span>
                </td>
            </tr>
        </table>";
    }
    else {
        $returnHTML = "<span><input type='text' id='".FIELD_VALUE_SETTING."_".$fieldCount."_".$choiceCount."' name='".FIELD_VALUE_SETTING."_$fieldCount'/></span>";
    }
}

echo $returnHTML;