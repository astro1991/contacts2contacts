#!/usr/bin/php

<?php

$start = microtime(true);

define("SRCUSER", "user1");
define("SRCPASSWORD", "password1");


define("DSTUSER", "user2");
define("DSTPASSWORD", "password2");


define("ZARAFASERVER", "file:///var/run/zarafa");
define("PHP_MAPI_PATH", "/usr/share/php/mapi/");

//include php-mapi files
require_once(PHP_MAPI_PATH.'mapi.util.php');
require_once(PHP_MAPI_PATH.'mapidefs.php');
require_once(PHP_MAPI_PATH.'mapicode.php');
require_once(PHP_MAPI_PATH.'mapitags.php');
require_once(PHP_MAPI_PATH.'mapiguid.php');

$srcsession = mapi_logon_zarafa(SRCUSER, SRCPASSWORD, ZARAFASERVER);
if (mapi_last_hresult()!=0) {
	trigger_error(sprintf("MAPI Error: 0x%x", mapi_last_hresult()), E_USER_ERROR);
	exit (1);
}

$dstsession = mapi_logon_zarafa(DSTUSER, DSTPASSWORD, ZARAFASERVER);
if (mapi_last_hresult()!=0) {
	trigger_error(sprintf("MAPI Error: 0x%x", mapi_last_hresult()), E_USER_ERROR);
	exit (1);
}

$srcstoresTable = mapi_getmsgstorestable($srcsession);
if (mapi_last_hresult()!=0) {
	trigger_error(sprintf("MAPI Error: 0x%x", mapi_last_hresult()), E_USER_ERROR);
	exit (1);
}

$srcstores = mapi_table_queryallrows($srcstoresTable);
if (mapi_last_hresult()!=0) {
	trigger_error(sprintf("MAPI Error: 0x%x", mapi_last_hresult()), E_USER_ERROR);
	exit (1);
}

$dststoresTable = mapi_getmsgstorestable($dstsession);
if (mapi_last_hresult()!=0) {
	trigger_error(sprintf("MAPI Error: 0x%x", mapi_last_hresult()), E_USER_ERROR);
	exit (1);
}

$dststores = mapi_table_queryallrows($dststoresTable);
if (mapi_last_hresult()!=0) {
	trigger_error(sprintf("MAPI Error: 0x%x", mapi_last_hresult()), E_USER_ERROR);
	exit (1);
}

for($i=0;$i<count($srcstores); $i++){
	if ($srcstores[$i][PR_MDB_PROVIDER] == ZARAFA_SERVICE_GUID){
		$srcstoreEntryid = $srcstores[$i][PR_ENTRYID];
	}
}
if (!isset($srcstoreEntryid)) {
	trigger_error("src-User store nicht gefunden", E_USER_ERROR);
	exit (1);
}

for($i=0;$i<count($dststores); $i++){
	if ($dststores[$i][PR_MDB_PROVIDER] == ZARAFA_SERVICE_GUID){
		$dststoreEntryid = $dststores[$i][PR_ENTRYID];
	}
}
if (!isset($dststoreEntryid)) {
	trigger_error("dst-User store nicht gefunden", E_USER_ERROR);
	exit (1);
}

$srcstore = mapi_openmsgstore($srcsession, $srcstoreEntryid);
$dststore = mapi_openmsgstore($dstsession, $dststoreEntryid);

$root = mapi_msgstore_openentry($srcstore);
$dstroot = mapi_msgstore_openentry($dststore);

$container = mapi_getprops($root,array(PR_IPM_CONTACT_ENTRYID));
$dstcontainer = mapi_getprops($dstroot,array(PR_IPM_CONTACT_ENTRYID));

if (isset($container[PR_IPM_CONTACT_ENTRYID])) 
	$contact_entryid = $container[PR_IPM_CONTACT_ENTRYID];

if (isset($dstcontainer[PR_IPM_CONTACT_ENTRYID])) 
	$dstcontact_entryid = $dstcontainer[PR_IPM_CONTACT_ENTRYID];

//$properties = array();
//$properties["given_name"] = PR_GIVEN_NAME; 
//$properties["surname"] = PR_SURNAME;
//$properties["company_name"] = PR_COMPANY_NAME;

$srcfolder = mapi_msgstore_openentry($srcstore, $contact_entryid);
$srctable = mapi_folder_getcontentstable($srcfolder);
$srclist = mapi_table_queryallrows($srctable);
//$list = mapi_table_queryallrows($srctable,$properties);
//print mapi_table_getrowcount($srctable) . "\n";

$dstfolder = mapi_msgstore_openentry($dststore, $dstcontact_entryid);
$dsttable = mapi_folder_getcontentstable($dstfolder);
$dstlist = mapi_table_queryallrows($dsttable);

$entryids        = array();
foreach ($srclist as $contact) {
	$entryids[] = $contact[PR_ENTRYID];
}

$dstentryids        = array();
foreach ($dstlist as $contact) {
	$dstentryids[] = $contact[PR_ENTRYID];
}

print "DEBUG - src-contacts:".count($entryids)."\n";
print "DEBUG - dst-contacts:".count($dstentryids)."\n";

if (!empty($dstentryids)) {
	$result = mapi_folder_emptyfolder($dstfolder, DELETE_HARD_DELETE);
	if ( ! $result ) {
		trigger_error( sprintf("Delete Destination Folder Error: %x\n", mapi_last_hresult()), E_USER_ERROR);
		exit(1);
	}
}

$result = mapi_folder_copymessages ($srcfolder, $entryids, $dstfolder);
if (mapi_is_error(mapi_last_hresult())) {
	trigger_error( sprintf("Copy Folder Error: %x\n", mapi_last_hresult()), E_USER_ERROR);
	exit(1);
}

print "DEBUG - Runtime:".(microtime(true)-$start)."\n";
print "DEBUG - End\n";
exit (0);

?>

