<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* memo Management section */

// key to authenticate
define('INDEX_AUTH', '1');
// key to get full database access
define('DB_ACCESS', 'fa');

if (!defined('SB')) {
  // main system configuration
  require '../../../sysconfig.inc.php';
  // start the session
  require SB.'admin/default/session.inc.php';
}
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');


require SB.'admin/default/session_check.inc.php';
require SIMBIO.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO.'simbio_UTILS/simbio_date.inc.php';
require SIMBIO.'simbio_FILE/simbio_file_upload.inc.php';

// privileges checking
$can_read = utility::havePrivilege('memo', 'r');
$can_write = utility::havePrivilege('memo', 'w');

if (!$can_read) {
    die('<div class="errorBox">You dont have enough privileges to view this section</div>');
}



/* memo update process */
if (isset($_POST['saveData']) AND $can_read AND $can_write) {
    // check form validity
   
    $memoTitle = trim($_POST['memo_title']);
   
    if (empty($memoTitle)) {
        utility::jsAlert(__('Memo Title can\'t be empty')); //mfc
        exit();
    } else if (($mpasswd1 OR $mpasswd2) AND ($mpasswd1 !== $mpasswd2)) {
        utility::jsAlert(__('Password confirmation does not match. See if your Caps Lock key is on!'));
        exit();
    } else {

        $data['memo_title'] = $memoTitle;
        $data['memo_content'] = $dbs->escape_string(trim($_POST['memo_content']));
        $data['memo_type_id'] = (integer)$_POST['memoTypeID'];
        $data['isshow'] = isset($_POST['isshow'])? intval($_POST['isshow']) : '0';
        $data['date_start'] = trim($dbs->escape_string(strip_tags($_POST['date_start'])));
        $data['date_start'] = $data['date_start'] == '' ? null : $data['date_start'];
        $data['date_end'] = trim($dbs->escape_string(strip_tags($_POST['date_end'])));
        $data['date_end'] = $data['date_end'] == '' ? null : $data['date_end'];
        $data['last_update'] = date('Y-m-d');
        $data['ip_address'] = $_SERVER['REMOTE_ADDR'];
         
        $users = '';
        if (isset($_POST['users']) AND !empty($_POST['users'])) {
            $users = serialize($_POST['users']);
              //  echo 'apem='.print_r($user);
             //var_dump($_POST);
            //exit();
        } else {
            $users = 'literal{NULL}';
        }
        $data['memo_receiver'] = trim($users);
        $data['user_id'] = $_SESSION['uid'];


        



        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) {
            /* UPDATE RECORD MODE */
            // remove input date
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = $dbs->escape_string(trim($_POST['updateRecordID']));
            $old_memo_ID = $updateRecordID;
            // update the data
            $update = $sql_op->update('memo', $data, "memo_id='$updateRecordID'");
            if ($update) {
                // update other tables contain this memo ID
                @$dbs->query('UPDATE loan SET memo_id=\''.$data['memo_id'].'\' WHERE memo_id=\''.$old_memo_ID.'\'');
                @$dbs->query('UPDATE fines SET memo_id=\''.$data['memo_id'].'\' WHERE memo_id=\''.$old_memo_ID.'\'');
                utility::jsAlert(__('memo Data Successfully Updated'));
                // upload status alert
                if (isset($upload_status)) {
                    if ($upload_status == UPLOAD_SUCCESS) {
                        // write log
                        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'memo', $_SESSION['realname'].' upload image file '.$upload->new_filename);
                        utility::jsAlert(__('Image Uploaded Successfully'));
                    } else {
                        // write log
                        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'memo', 'ERROR : '.$_SESSION['realname'].' FAILED TO upload image file '.$upload->new_filename.', with error ('.$upload->error.')');
                        utility::jsAlert(__('Image FAILED to upload'));
                    }
                }
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'memo', $_SESSION['realname'].' update memo data ('.$memoName.') with ID ('.$memoID.')');
                if ($sysconf['webcam'] == 'html5') {
                  echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.MWB.'memo/index.php\');</script>';
                } else {
                  echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.MWB.'memo/index.php\');</script>';
                }
            } else { utility::jsAlert(__('memo Data FAILED to Save/Update. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
            exit();
        } else {
           
            // insert the data
            $insert = $sql_op->insert('memo', $data);
            if ($insert) {
                utility::jsAlert(__('New memo Data Successfully Saved'));
                // upload status alert
                if (isset($upload_status)) {
                    if ($upload_status == UPLOAD_SUCCESS) {
                        // write log
                        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'memo', $_SESSION['realname'].' upload image file '.$upload->new_filename);
                        utility::jsAlert(__('Image Uploaded Successfully'));
                    } else {
                        // write log
                        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'memo', 'ERROR : '.$_SESSION['realname'].' FAILED TO upload image file '.$upload->new_filename.', with error ('.$upload->error.')');
                        utility::jsAlert(__('Image FAILED to upload'));
                    }
                }
                // write log
                utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'memo', $_SESSION['realname'].' add new memo ('.$memoName.') with ID ('.$memoID.')');
                echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'\');</script>';
            } else { utility::jsAlert(__('memo Data FAILED to Save/Update. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
            exit();
        }
    }
    exit();
} else if (isset($_POST['batchExtend']) && $can_read && $can_write) {
    /* BATCH extend memo proccessing */
    $curr_date = date('Y-m-d');
    $num_extended = 0;
    foreach ($_POST['itemID'] as $itemID) {
        $memoID = $dbs->escape_string(trim($itemID));
        // get memo periode from database
        $mtype_q = $dbs->query('SELECT memo_periode, m.memo_content FROM memo AS m
            LEFT JOIN mst_memo_type AS mt ON m.memo_type_id=mt.memo_type_id 
            WHERE m.memo_id=\''.$memoID.'\'');
        $mtype_d = $mtype_q->fetch_row();
        $expire_date = simbio_date::getNextDate($mtype_d[0], $curr_date);
        @$dbs->query('UPDATE memo SET expire_date=\''.$expire_date.'\' WHERE memo_id=\''.$memoID.'\'');
        // write log
        utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'memo', $_SESSION['realname'].' extends memo for memo ('.$mtype_d[1].') with ID ('.$memoID.')');
        $num_extended++;
    }
    header('Location: '.MWB.'memo/index.php?expire=true&numExtended='.$num_extended);
    exit();
} else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!($can_read AND $can_write)) {
        die();
    }
     /* DATA DELETION PROCESS */
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array((integer)$_POST['itemID']);
    }
    // loop array
    foreach ($_POST['itemID'] as $itemID) {
        $itemID = (integer)$itemID;
        if (!$sql_op->delete('memo', 'memo_id='.$itemID)) {
            $error_num++;
        }
    }


    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(__('All Data Successfully Deleted'));
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\');</script>';
    } else {
        utility::jsAlert(__('Some or All Data NOT deleted successfully!\nPlease contact system administrator'));
        echo '<script type="text/javascript">parent.$(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\');</script>';
    }
    exit();
}
/* RECORD OPERATION END */

/* search form */
?>

<fieldset class="menuBox">
<div class="menuBoxInner memoIcon">
	<div class="per_title">
    	<h2><?php echo __('Memo'); ?></h2>
    </div>
    <div class="sub_section">
	<div class="btn-group">
    <a href="<?php echo MWB; ?>memo/index.php" class="btn btn-default"><i class="glyphicon glyphicon-list-alt"></i>&nbsp;<?php echo __('Memo List'); ?></a>
    <a href="<?php echo MWB; ?>memo/index.php?expire=true" class="btn btn-default" style="color: #FF0000;"><i class="glyphicon glyphicon-list-alt"></i>&nbsp;<?php echo __('View Expired memo'); ?></a>
    <a href="<?php echo MWB; ?>memo/index.php?action=detail" class="btn btn-default"><i class="glyphicon glyphicon-plus"></i>&nbsp;<?php echo __('Add New Memo'); ?></a>
	</div>
    <form name="search" action="<?php echo MWB; ?>memo/index.php" id="search" method="get" style="display: inline;"><?php echo __('Memo Search'); ?> :
	    <input type="text" name="keywords" size="30" /><?php if (isset($_GET['expire'])) { echo '<input type="hidden" name="expire" value="true" />'; } ?>
	    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="button" />
	</form>
	</div>
</div>
</fieldset>

<?php
/* search form end */
/* main content */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.__('You don\'t have enough privileges to view this section').'</div>');
    }
    /* RECORD FORM */
    $itemID = $dbs->escape_string(trim(isset($_POST['itemID'])?$_POST['itemID']:''));
    $rec_q = $dbs->query("SELECT * FROM memo WHERE memo_id='$itemID'");
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="button"';

    // form table attributes
    $form->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
    $form->table_content_attr = 'class="alterCell2"';


    // edit mode flag set
    if ($rec_q->num_rows > 0) {
        $form->edit_mode = true;
        // record ID for delete process
        $form->record_id = $itemID;
        // form record title
        $form->record_title = $rec_d['memo_name'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="button"';
    }

    /* Form Element(s) */
    if ($form->edit_mode) {
        // check if memo expired
        $curr_date = date('Y-m-d');
        $compared_date = simbio_date::compareDates($rec_d['date_end'], $curr_date);
        $is_expired = ($compared_date == $curr_date);
        $expired_message = '';
        if ($is_expired) {
            $expired_message = '<b style="color: #FF0000;">('.__('memo Already Expired').')</b>';
        }
    }

    // memo type
    // get mtype data related to this record from database
    $mtype_query = $dbs->query("SELECT memo_type_id, memo_type_name FROM mst_memo_type");
    $mtype_options = array();
    $mtype_options[] = array('', '');
    while ($mtype_data = $mtype_query->fetch_row()) {
        $mtype_options[] = array($mtype_data[0], $mtype_data[1]);
    }
    $form->addSelectList('memoTypeID', __('Memo Type').'*', $mtype_options, $rec_d['memo_type_id'],'');

    // memo pin
    $form->addTextField('text', 'memo_title', __('Memo Title'), $rec_d['memo_title'], 'style="width: 100%;"');
    // memo notes
    $form->addTextField('textarea', 'memo_content', __('Memo Content'), htmlentities($rec_d['memo_content'], ENT_QUOTES), 'class="texteditor" tyle="width: 100%; height: 500px;"');

    // memo is_pending
    $form->addCheckBox('isshow', __('Show on Dashboard'), array( array('1', __('Yes')) ), $rec_d['isshow']);
     // memo Start date
    $form->addDateField('date_start', __('Start Date').'*', $form->edit_mode?$rec_d['date_start']:date('Y-m-d'));
     // memo End date
    $form->addDateField('date_end', __('End Date').'*', $form->edit_mode?$rec_d['date_end']:date('Y-m-d'));
    
    // user group
    // only appear by user who hold system module privileges
    if ($can_read AND $can_write) {
        // add hidden element as a flag that we dont change group data
        $form->addHidden('noChangeUser', '1');
        // user group
        $user_query = $dbs->query('SELECT user_id, realname FROM
            user WHERE user_id NOT IN ("1") ');
        // initiliaze group options
        $user_options = array();
        while ($user_data = $user_query->fetch_row()) {
            $user_options[] = array($user_data[0], $user_data[1]);
        }
        $form->addCheckBox('users', __('User(s) '), $user_options, unserialize($rec_d['memo_receiver']));
    }

    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'
            .'<div style="float: left; width: 80%;">'.__('You are going to edit memo data').' : <b>'.$rec_d['memo_title'].'</b> <br />'.__('Last Updated').' '.$rec_d['last_update'].' '.$expired_message
            .'</div>';
        echo '</div>'."\n";
    }
    // print out the form object
    echo $form->printOut();
?>
 <script type="text/javascript">

            CKEDITOR.replace('memo_content');
             $(document).bind('formEnabled', function() {
                CKEDITOR.instances.memo_content.setReadOnly(false);
            });
         
</script>
<?php
} else {
    /* memo LIST */
    // table spec
    $table_spec = 'memo AS m
        LEFT JOIN mst_memo_type AS mt ON m.memo_type_id=mt.memo_type_id
        LEFT JOIN user AS u ON u.user_id=m.user_id';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn('m.memo_id',
           
            'm.memo_title AS \''.__('Memo Title').'\'',
            'mt.memo_type_name AS \''.__('memo Type').'\'',
            'm.date_start AS \''.__('Start').'\'',
            'm.date_end AS \''.__('End').'\'',
            'u.realname AS \''.__('Memo Created by').'\'');
    } else {
        $datagrid->setSQLColumn(
            'm.memo_name AS \''.__('memo Name').'\'',
            'mt.memo_type_name AS \''.__('memo Type').'\'',
            'm.date_start AS \''.__('Start').'\'',
            'm.date_end AS \''.__('End').'\'',
            'u.realname AS \''.__('Memo Created by').'\'');
    }
    $datagrid->setSQLorder('memo_id DESC');



    // is there any search
    $criteria = 'm.memo_id IS NOT NULL ';
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $criteria .= " AND (m.memo_title LIKE '%$keywords%' OR m.memo_content LIKE '%$keywords%') ";
    }

    if($_SESSION['uid'] != 1){
         $criteria .= " AND m.user_id = '".$_SESSION['uid']."'";
    }
    
    $datagrid->setSQLCriteria($criteria);

    // set table and table header attributes
    $datagrid->icon_edit = SWB.'admin/'.$sysconf['admin_template']['dir'].'/'.$sysconf['admin_template']['theme'].'/edit.gif';
    $datagrid->table_name = 'memoList';
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));
    if ((isset($_GET['keywords']) AND $_GET['keywords']) OR isset($_GET['expire'])) {
        echo '<div class="infoBox">';
        if (isset($_GET['keywords']) AND $_GET['keywords']) {
            echo __('Found').' '.$datagrid->num_rows.' '.__('from your search with keyword').' : "'.$_GET['keywords'].'"'; //mfc
        }
        echo '</div>';
    }

    echo $datagrid_result;
}
/* main content end */
