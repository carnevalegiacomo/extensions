<?php
/**
 * IntegratePerson extension - Integrates Person records into user preferences and account creation forms
 *
 * @package MediaWiki
 * @subpackage Extensions
 * @author Aran Dunkley [http://www.organicdesign.co.nz/nad User:Nad]
 * @licence GNU General Public Licence 2.0 or later
 */

# Check dependency extensions
if ( !defined( 'MEDIAWIKI' ) )                     die( 'Not an entry point.' );
if ( !defined( 'RECORDADMIN_VERSION' ) )           die( 'This extension depends on the RecordAdmin extension' );
if ( !defined( 'JAVASCRIPT_VERSION' ) )            die( 'This extension depends on the JavaScript extension' );

# Ensure running at least MediaWiki version 1.16
if ( version_compare( substr( $wgVersion, 0, 4 ), '1.16' ) < 0 )
	die( "Sorry, this extension requires at least MediaWiki version 1.16 (this is version $wgVersion)" );


define( 'RAINTEGRATEPERSON_VERSION', '0.2.9, 2009-11-17' );

$wgAutoConfirmCount = 10^10;

$wgIPDefaultImage = '';
$wgIPMaxImageSize = 100000;
$wgIPPersonType   = 'Person';

$wgExtensionFunctions[] = 'wfSetupRAIntegratePerson';
$wgExtensionCredits['other'][] = array(
	'name'        => 'RecordAdminIntegratePerson',
	'author'      => '[http://www.organicdesign.co.nz/User:Nad User:Nad]',
	'description' => 'Integrates Person records (see RecordAdmin extension) into user preferences and account creation forms',
	'url'         => 'http://www.organicdesign.co.nz/Extension:IntegratePerson',
	'version'     => RAINTEGRATEPERSON_VERSION
);

# Process posted contact details
if ( isset( $_POST['wpFirstName'] ) ) $_POST['wpRealName'] = $_POST['wpFirstName'] . ' ' . $_POST['wpLastName'];

class RAIntegratePerson {

	function __construct() {
		global $wgRequest, $wgTitle, $wgHooks, $wgMessageCache, $wgParser, $wgSpecialRecordAdmin;

		# Modify login form messages to say email and name compulsory
#		$wgMessageCache->addMessages(array('prefs-help-email' => '<div class="error">Required</div>'));
#		$wgMessageCache->addMessages(array('prefs-help-realname' => '<div class="error">Required</div>'));

		$wgHooks['PersonalUrls'][] = $this;

		$title = $wgSpecialRecordAdmin->title = Title::newFromText( $wgRequest->getText( 'title' ) );
		if ( !is_object( $wgTitle ) ) $wgTitle = $title;
		if ( is_object( $title ) ) {

			# Hook rendering mods into prefs
			if ( $title->getPrefixedText() == 'Special:Preferences' ) {
				$wgHooks['BeforePageDisplay'][] = array( $this, 'modPreferences' );
				$this->processForm();
			}

			# Hook rendering mods into account-creation
			if ( $title->getPrefixedText() == 'Special:UserLogin' && $wgRequest->getText( 'type' ) == 'signup' ) {
				$wgHooks['BeforePageDisplay'][] = array( $this, 'modAccountCreate' );
				$this->processForm();
			}
		}

		# Process an uploaded profile image if one was posted
		if ( array_key_exists( 'wpIPImage', $_FILES ) && $_FILES['wpIPImage']['size'] > 0 )
			$this->processUploadedImage( $_FILES['wpIPImage'] );

	}

	/**
	 * Modify personal URL's
	 */
	function onPersonalUrls( &$urls, &$title ) {
		global $wgUser;
		if ( $person = $wgUser->getRealName() ) {
			$userpage = array_shift( $urls );
			$talkpage = array_shift( $urls );
			$mycat    = str_replace( '$1', $person, '/Category:$1' );
			$mywork   = str_replace( '$1', $person, '/wiki/index.php?title=Category:Activities&Person=$1' );
			$urls     = array(
				'userpage' => $userpage,
				'talkpage' => $talkpage,
				'mycat'    => array( 'text' => 'My category', 'href' => $mycat  ),
				'mywork'   => array( 'text' => 'My worklog',  'href' => $mywork )
			) + $urls;
		}
		return true;
	}

	/**
	 * Modify the prefs page
	 */
	function modPreferences( &$out, $skin = false ) {
		global $wgJsMimeType;

		# Add JS
		$out->addScript( "<script type='$wgJsMimeType'>
			function ipSubmit() {
			}
			function ipOnload() {

				// Hide fieldsets
				$('fieldset#prefsection-0 fieldset:nth-child(6)').hide(); // internationalisation
				$('fieldset#prefsection-0 fieldset:nth-child(7)').hide(); // signature
				$('fieldset#prefsection-0 fieldset:nth-child(8)').hide(); // email options

				// Defaults for the hidden email options
				$('#mw-input-enotifwatchlistpages').attr('checked','yes');
				$('#mw-input-enotifusertalkpages').attr('checked','yes');
				$('#mw-input-enotifminoredits').attr('checked','');
				$('#wpEmailFlag').attr('checked','');
				$('#mw-input-ccmeonemails').attr('checked','');

				// Hide items in the Basic Information fieldset
				$('table#mw-htmlform-info tr:nth-child(6)').hide(); // real name
				$('table#mw-htmlform-info tr:nth-child(7)').hide(); // real name comment
				$('table#mw-htmlform-info tr:nth-child(8)').hide(); // gender
				$('table#mw-htmlform-info tr:nth-child(9)').hide(); // gender comment
				
			}
			addOnloadHook(ipOnload);
		</script>" );

		# Modify the forms enctype to allow uploaded image
		$out->mBodytext = str_replace(
			'<form',
			'<form onsubmit="return ipSubmit(this)" enctype="multipart/form-data"',
			$out->mBodytext
		);

		# Integrate the Person record
		$form = $this->getForm();
		$out->mBodytext = preg_replace(
			"|(<fieldset>\s*<legend>Internationalisation)|s",
			"$form$1",
			$out->mBodytext
		);

		return true;
	}

	/**
	 * Modify the account-create page
	 */
	function modAccountCreate( &$out, $skin = false ) {
		global $wgJsMimeType;
		
		# Add JS
		$out->addScript( "<script type='$wgJsMimeType'>
			function ipSubmit() {
				document.getElementById('wpRealName').value = document.getElementById('FirstName').value + ' ' + document.getElementById('Surname').value
			}
			function ipOnload() {
				
				// Hide items in the current form
				$('fieldset#login table tr:nth-child(4)').hide(); // email
				$('fieldset#login table tr:nth-child(5)').hide(); // real name
				$('fieldset#login table tr:nth-child(7)').hide(); // submit buttons
			}
			addOnloadHook(ipOnload);
		</script>" );

		# Modify the forms enctype to allow uploaded image
		$out->mBodytext = str_replace(
			'<form',
			'<form onsubmit="return ipSubmit(this)" enctype="multipart/form-data"',
			$out->mBodytext
		);

		# Integrate the Person record and add new submits at the bottom
		$form = $this->getForm();
		$submit = '<input type="submit" name="wpCreateaccount" id="wpCreateaccount" value="Create account" />';
		$submit .= '<input type="submit" name="wpCreateaccountMail" id="wpCreateaccountMail" value="by e-mail" />';
		$out->mBodytext = preg_replace(
			"|(<table.+?</table>)|s",
			"<fieldset id='login'><legend>Login details</legend>$1</fieldset>$form$submit",
			$out->mBodytext
		);

		return true;
	}

	/**
	 * Get the HTML for the Person form from RecordAdmin
	 */
	function getForm() {
		global $wgSpecialRecordAdmin, $wgIPPersonType, $wgUser;

		# Use RecordAdmin to create, examine and populate the form
		$wgSpecialRecordAdmin->preProcessForm( $wgIPPersonType );
		$wgSpecialRecordAdmin->examineForm();

		# If the user has a Person record, populate the form with its data
		$title = Title::newFromText( $wgUser->getRealName() );
		if ( is_object( $title ) && $title->exists() ) {
			$record = new Article( $title );
			$record = $record->getContent();
			$wgSpecialRecordAdmin->populateForm( $record );
		}

		# Return the form minus the Adminstration section
		return preg_replace( "|(^.+)<tr.+?Administration.+$|ms", "$1</table></td></tr></table></fieldset>", $wgSpecialRecordAdmin->form );
	}

	/**
	 * Process any posted inputs from the Person record
	 */
	function processForm( ) {
		global $wgUser, $wgSpecialRecordAdmin, $wgIPPersonType;

		# Update the record values from posted data
		$this->getForm();
		$posted = false;
		foreach ( $_REQUEST as $k => $v ) if ( preg_match( '|^ra_(\\w+)|', $k, $m ) ) {
			$k = $m[1];
			if ( isset( $wgSpecialRecordAdmin->types[$k] ) ) {
				if ( is_array( $v ) ) $v = join( "\n", $v );
				elseif ( $wgSpecialRecordAdmin->types[$k] == 'bool' ) $v = 'yes';
				$wgSpecialRecordAdmin->values[$k] = $v;
				$posted = true;
			}
		}

		# If any values were posted update or ceate the record
		if ( $posted ) {

			# Get the title if the users Person record and bail if invalid
			$name = $wgSpecialRecordAdmin->values['FirstName'] . ' ' . $wgSpecialRecordAdmin->values['Surname'];
			$title = Title::newFromText( $name );
			if ( !is_object( $title ) ) return false;

			# Construct the record brace text
			$record = '';
			foreach ( $wgSpecialRecordAdmin->values as $k => $v ) $record .= "| $k = $v\n";
			$record = "{{" . "$wgIPPersonType\n$record}}";

			# Create or update the article
			$page = $_REQUEST['title'];
			$article = new Article( $title );
			if ( $title->exists() ) {
				$text = $article->getContent();
				foreach ( $wgSpecialRecordAdmin->examineBraces( $text ) as $brace ) if ( $brace['NAME'] == $wgIPPersonType ) $braces = $brace;
				$text = substr_replace( $text, $record, $braces['OFFSET'], $braces['LENGTH'] );
				$success = $article->doEdit( $text, "Record updated via $page", EDIT_UPDATE );
			} else $success = $article->doEdit( $record, "Record created via $page", EDIT_NEW );

		}
	}
	
	/**
	 * Process uploaded image file
	 */
	function processUploadedImage( $file ) {
		global $wgUser, $wgDBname, $wgSiteNotice, $wgUploadDirectory, $wgIPMaxImageSize;
		$error = false;
		if ( !ereg( '^image/(jpeg|png|gif)$', $file['type'] ) ) $error = 'Uploaded file was not of a valid type!';
		if ( $file['size'] > $wgIPMaxImageSize )                $error = 'Profile images are restricted to a maximum of 100KBytes';
		if ( $file['error'] > 0 )                               $error = 'Uploaded error number ' . $file['error'] . ' occurred';
		if ( $error ) $wgSiteNotice = "<div class='errorbox'>$error</div>";
		else {
			$name = preg_replace( '%.+(\..+?)$%', "user-{$wgDBname}-{$wgUser->getId()}$1", $file['name'] );
			move_uploaded_file( $file['tmp_name'], "$wgUploadDirectory/$name" );
		}
	}

}

function wfSetupRAIntegratePerson() {
	global $wgRAIntegratePerson, $wgLanguageCode, $wgMessageCache;

	# Add the messages used by the specialpage
	if ( $wgLanguageCode == 'en' ) {
		$wgMessageCache->addMessages( array(
			'ip-preftab'   => "Person Record",
			'ip-prefmsg'   => "<br><b>Fill in your Personal details here...</b><br>"
		) );
	}

	# Instantiate the IntegratePerson singleton now that the environment is prepared
	$wgRAIntegratePerson = new RAIntegratePerson();

}