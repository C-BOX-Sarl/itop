<?php
/**
 * Copyright (C) 2013-2020 Combodo SARL
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 */

use Combodo\iTop\Application\TwigBase\Twig\TwigHelper;

/**
 * Generic interface common to CLI and Web pages
 */
Interface Page
{
	/**
	 * Outputs (via some echo) the complete HTML page by assembling all its elements
	 *
	 * @return mixed
	 */
	public function output();

	/**
	 * Add any text or HTML fragment to the body of the page
	 *
	 * @param string $sText
	 *
	 * @return mixed
	 */
	public function add($sText);

	/**
	 * Add a paragraph to the body of the page
	 *
	 * @param string $sText
	 *
	 * @return mixed
	 */
	public function p($sText);

	/**
	 * Add a pre-formatted text to the body of the page
	 *
	 * @param string $sText
	 *
	 * @return mixed
	 */
	public function pre($sText);

	/**
	 * Add a comment
	 *
	 * @param string $sText
	 *
	 * @return mixed
	 */
	public function add_comment($sText);

	/**
	 * Adds a tabular content to the web page
	 *
	 * @param string[] $aConfig Configuration of the table: hash array of 'column_id' => 'Column Label'
	 * @param string[] $aData Hash array. Data to display in the table: each row is made of 'column_id' => Data. A
	 *     column 'pkey' is expected for each row
	 * @param array $aParams Hash array. Extra parameters for the table.
	 *
	 * @return void
	 */
	public function table($aConfig, $aData, $aParams = array());
}


/**
 * <p>Simple helper class to ease the production of HTML pages
 *
 * <p>This class provide methods to add content, scripts, includes... to a web page
 * and renders the full web page by putting the elements in the proper place & order
 * when the output() method is called.
 *
 * <p>Usage:
 * ```php
 *    $oPage = new WebPage("Title of my page");
 *    $oPage->p("Hello World !");
 *    $oPage->output();
 * ```
 */
class WebPage implements Page
{
	/**
	 * @since 2.7.0 N°2529
	 */
	const PAGES_CHARSET = 'utf-8';
	protected $s_title;
	protected $s_content;
	protected $s_deferred_content;
	protected $a_scripts;
	protected $a_dict_entries;
	protected $a_dict_entries_prefixes;
	protected $a_styles;
	protected $a_linked_scripts;
	protected $a_linked_stylesheets;
	protected $a_headers;
	protected $a_base;
	protected $iNextId;
	protected $iTransactionId;
	protected $sContentType;
	protected $sContentDisposition;
	protected $sContentFileName;
	protected $bTrashUnexpectedOutput;
	protected $s_sOutputFormat;
	protected $a_OutputOptions;
	protected $bPrintable;
	protected $bHasCollapsibleSection;
	protected $bAddJSDict;

	/**
	 * WebPage constructor.
	 *
	 * @param string $s_title
	 * @param bool $bPrintable
	 */
	public function __construct($s_title, $bPrintable = false)
	{
		$this->s_title = $s_title;
		$this->s_content = "";
		$this->s_deferred_content = '';
		$this->a_scripts = array();
		$this->a_dict_entries = array();
		$this->a_dict_entries_prefixes = array();
		$this->a_styles = array();
		$this->a_linked_scripts = array();
		$this->a_linked_stylesheets = array();
		$this->a_headers = array();
		$this->a_base = array('href' => '', 'target' => '');
		$this->iNextId = 0;
		$this->iTransactionId = 0;
		$this->sContentType = '';
		$this->sContentDisposition = '';
		$this->sContentFileName = '';
		$this->bTrashUnexpectedOutput = false;
		$this->s_OutputFormat = utils::ReadParam('output_format', 'html');
		$this->a_OutputOptions = array();
		$this->bHasCollapsibleSection = false;
		$this->bPrintable = $bPrintable;
		$this->bAddJSDict = true;
		ob_start(); // Start capturing the output
	}

	/**
	 * Change the title of the page after its creation
	 *
	 * @param string $s_title
	 */
	public function set_title($s_title)
	{
		$this->s_title = $s_title;
	}

	/**
	 * Specify a default URL and a default target for all links on a page
	 *
	 * @param string $s_href
	 * @param string $s_target
	 */
	public function set_base($s_href = '', $s_target = '')
	{
		$this->a_base['href'] = $s_href;
		$this->a_base['target'] = $s_target;
	}

	/**
	 * @inheritDoc
	 */
	public function add($s_html)
	{
		$this->s_content .= $s_html;
	}

	/**
	 * Add any rendered text or HTML fragment to the body of the page using a twig template
	 *
	 * @param string $sViewPath Absolute path of the templates folder
	 * @param string $sTemplateName Name of the twig template, ie MyTemplate for MyTemplate.html.twig
	 * @param array $aParams Params used by the twig template
	 * @param string $sDefaultType default type of the template ('html', 'xml', ...)
	 *
	 * @throws \Exception
	 */
	public function add_twig_template($sViewPath, $sTemplateName, $aParams = array(), $sDefaultType = 'html')
	{
		TwigHelper::RenderIntoPage($this, $sViewPath, $sTemplateName, $aParams, $sDefaultType);
	}

	/**
	 * Add any text or HTML fragment (identified by an ID) at the end of the body of the page
	 * This is useful to add hidden content, DIVs or FORMs that should not
	 * be embedded into each other.
	 *
	 * @param string $s_html
	 * @param string $sId
	 */
	public function add_at_the_end($s_html, $sId = '')
	{
		$this->s_deferred_content .= $s_html;
	}

	/**
	 * @inheritDoc
	 */
	public function p($s_html)
	{
		$this->add($this->GetP($s_html));
	}

	/**
	 * @inheritDoc
	 */
	public function pre($s_html)
	{
		$this->add('<pre>'.$s_html.'</pre>');
	}

	/**
	 * @inheritDoc
	 */
	public function add_comment($sText)
	{
		$this->add('<!--'.$sText.'-->');
	}

	/**
	 * Add a paragraph to the body of the page
	 *
	 * @param string $s_html
	 *
	 * @return string
	 */
	public function GetP($s_html)
	{
		return "<p>$s_html</p>\n";
	}

	/**
	 * @inheritDoc
	 * @throws \Exception
	 */
	public function table($aConfig, $aData, $aParams = array())
	{
		$this->add($this->GetTable($aConfig, $aData, $aParams));
	}

	/**
	 * @param array $aConfig
	 * @param array $aData
	 * @param array $aParams
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function GetTable($aConfig, $aData, $aParams = array())
	{
		$oAppContext = new ApplicationContext();

		static $iNbTables = 0;
		$iNbTables++;
		$sHtml = "";
		$sHtml .= "<table class=\"listResults\">\n";
		$sHtml .= "<thead>\n";
		$sHtml .= "<tr>\n";
		foreach ($aConfig as $sName => $aDef)
		{
			$sHtml .= "<th title=\"".$aDef['description']."\">".$aDef['label']."</th>\n";
		}
		$sHtml .= "</tr>\n";
		$sHtml .= "</thead>\n";
		$sHtml .= "<tbody>\n";
		foreach ($aData as $aRow)
		{
			$sHtml .= $this->GetTableRow($aRow, $aConfig);
		}
		$sHtml .= "</tbody>\n";
		$sHtml .= "</table>\n";

		return $sHtml;
	}

	/**
	 * @param array $aRow
	 * @param array $aConfig
	 *
	 * @return string
	 */
	public function GetTableRow($aRow, $aConfig)
	{
		$sHtml = '';
		if (isset($aRow['@class'])) // Row specific class, for hilighting certain rows
		{
			$sHtml .= "<tr class=\"{$aRow['@class']}\">";
		}
		else
		{
			$sHtml .= "<tr>";
		}
		foreach ($aConfig as $sName => $aAttribs)
		{
			$sClass = isset($aAttribs['class']) ? 'class="'.$aAttribs['class'].'"' : '';

			// Prepare metadata
			// - From table config.
			$sMetadata = '';
			if(isset($aAttribs['metadata']))
			{
				foreach($aAttribs['metadata'] as $sMetadataProp => $sMetadataValue)
				{
					$sMetadataPropSanitized = str_replace('_', '-', $sMetadataProp);
					$sMetadataValueSanitized = utils::HtmlEntities($sMetadataValue);
					$sMetadata .= 'data-'.$sMetadataPropSanitized.'="'.$sMetadataValueSanitized.'" ';
				}
			}

			// Prepare value
			if(is_array($aRow[$sName]))
			{
				$sValueHtml = ($aRow[$sName]['value_html'] === '') ? '&nbsp;' : $aRow[$sName]['value_html'];
				$sMetadata .= 'data-value-raw="'.utils::HtmlEntities($aRow[$sName]['value_raw']).'" ';
			}
			else
			{
				$sValueHtml = ($aRow[$sName] === '') ? '&nbsp;' : $aRow[$sName];
			}

			$sHtml .= "<td $sClass $sMetadata>$sValueHtml</td>";
		}
		$sHtml .= "</tr>";

		return $sHtml;
	}

	/**
	 * Add some Javascript to the header of the page
	 *
	 * @param string $s_script
	 */
	public function add_script($s_script)
	{
		$this->a_scripts[] = $s_script;
	}

	/**
	 * Add some Javascript to the header of the page
	 *
	 * @param $s_script
	 */
	public function add_ready_script($s_script)
	{
		// Do nothing silently... this is not supported by this type of page...
	}

	/**
	 * Allow a dictionnary entry to be used client side with Dict.S()
	 *
	 * @param string $s_entryId a translation label key
	 *
	 * @see \WebPage::add_dict_entries()
	 * @see utils.js
	 */
	public function add_dict_entry($s_entryId)
	{
		$this->a_dict_entries[] = $s_entryId;
	}

	/**
	 * Add a set of dictionary entries (based on the given prefix) for the Javascript side
	 *
	 * @param string $s_entriesPrefix translation label prefix (eg 'UI:Button:' to add all keys beginning with this)
	 *
	 * @see \WebPage::add_dict_entry()
	 * @see utils.js
	 */
	public function add_dict_entries($s_entriesPrefix)
	{
		$this->a_dict_entries_prefixes[] = $s_entriesPrefix;
	}

	/**
	 * @return string
	 */
	protected function get_dict_signature()
	{
		return str_replace('_', '', Dict::GetUserLanguage()).'-'.md5(implode(',',
					$this->a_dict_entries).'|'.implode(',', $this->a_dict_entries_prefixes));
	}

	/**
	 * @return string
	 */
	protected function get_dict_file_content()
	{
		$aEntries = array();
		foreach ($this->a_dict_entries as $sCode)
		{
			$aEntries[$sCode] = Dict::S($sCode);
		}
		foreach ($this->a_dict_entries_prefixes as $sPrefix)
		{
			$aEntries = array_merge($aEntries, Dict::ExportEntries($sPrefix));
		}
		$sJSFile = 'var aDictEntries = '.json_encode($aEntries);

		return $sJSFile;
	}


	/**
	 * Add some CSS definitions to the header of the page
	 *
	 * @param string $s_style
	 */
	public function add_style($s_style)
	{
		$this->a_styles[] = $s_style;
	}

	/**
	 * Add a script (as an include, i.e. link) to the header of the page.<br>
	 * Handles duplicates : calling twice with the same script will add the script only once
	 *
	 * @param string $s_linked_script
	 */
	public function add_linked_script($s_linked_script)
	{
		$this->a_linked_scripts[$s_linked_script] = $s_linked_script;
	}

	/**
	 * Add a CSS stylesheet (as an include, i.e. link) to the header of the page
	 *
	 * @param string $s_linked_stylesheet
	 * @param string $s_condition
	 */
	public function add_linked_stylesheet($s_linked_stylesheet, $s_condition = "")
	{
		$this->a_linked_stylesheets[] = array('link' => $s_linked_stylesheet, 'condition' => $s_condition);
	}

	/**
	 * @param string $sSaasRelPath
	 *
	 * @throws \Exception
	 */
	public function add_saas($sSaasRelPath)
	{
		$sCssRelPath = utils::GetCSSFromSASS($sSaasRelPath);
		$sRootUrl = utils::GetAbsoluteUrlAppRoot();
		if ($sRootUrl === '')
		{
			// We're running the setup of the first install...
			$sRootUrl = '../';
		}
		$sCSSUrl = $sRootUrl.$sCssRelPath;
		$this->add_linked_stylesheet($sCSSUrl);
	}

	/**
	 * Add some custom header to the page
	 *
	 * @param string $s_header
	 */
	public function add_header($s_header)
	{
		$this->a_headers[] = $s_header;
	}

	/**
	 * @param string|null $sHeaderValue for example `SAMESITE`. If null will set the header using the config parameter value.
	 *
	 * @since 2.7.3 3.0.0 N°3416
	 * @uses security_header_xframe config parameter
	 * @uses \utils::GetConfig()
	 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Frame-Options
	 */
	public function add_xframe_options($sHeaderValue = null)
	{
		if (is_null($sHeaderValue)) {
			$sHeaderValue = utils::GetConfig()->Get('security_header_xframe');
		}

		$this->add_header('X-Frame-Options: '.$sHeaderValue);
	}

	/**
	 * Add needed headers to the page so that it will no be cached
	 */
	public function no_cache()
	{
		$this->add_header('Cache-control: no-cache, no-store, must-revalidate');
		$this->add_header('Pragma: no-cache');
		$this->add_header('Expires: 0');
	}

	/**
	 * Build a special kind of TABLE useful for displaying the details of an object from a hash array of data
	 *
	 * @param array $aFields
	 */
	public function details($aFields)
	{

		$this->add($this->GetDetails($aFields));
	}

	/**
	 * Whether or not the page is a PDF page
	 *
	 * @return boolean
	 */
	public function is_pdf()
	{
		return false;
	}

	/**
	 * Records the current state of the 'html' part of the page output
	 *
	 * @return mixed The current state of the 'html' output
	 */
	public function start_capture()
	{
		return strlen($this->s_content);
	}

	/**
	 * Returns the part of the html output that occurred since the call to start_capture
	 * and removes this part from the current html output
	 *
	 * @param $offset mixed The value returned by start_capture
	 *
	 * @return string The part of the html output that was added since the call to start_capture
	 */
	public function end_capture($offset)
	{
		$sCaptured = substr($this->s_content, $offset);
		$this->s_content = substr($this->s_content, 0, $offset);

		return $sCaptured;
	}

	/**
	 * Build a special kind of TABLE useful for displaying the details of an object from a hash array of data
	 *
	 * @param array $aFields
	 *
	 * @return string
	 */
	public function GetDetails($aFields)
	{
		$aPossibleAttFlags = MetaModel::EnumPossibleAttributeFlags();

		$sHtml = "<div class=\"details\">\n";
		foreach ($aFields as $aAttrib)
		{
			$sLayout = isset($aAttrib['layout']) ? $aAttrib['layout'] : 'small';

			// Prepare metadata attributes
			$sDataAttributeCode = isset($aAttrib['attcode']) ? 'data-attribute-code="'.$aAttrib['attcode'].'"' : '';
			$sDataAttributeType = isset($aAttrib['atttype']) ? 'data-attribute-type="'.$aAttrib['atttype'].'"' : '';
			$sDataAttributeLabel = isset($aAttrib['attlabel']) ? 'data-attribute-label="'.utils::HtmlEntities($aAttrib['attlabel']).'"' : '';
			// - Attribute flags
			$sDataAttributeFlags = '';
			if(isset($aAttrib['attflags']))
			{
				foreach($aPossibleAttFlags as $sFlagCode => $iFlagValue)
				{
					// Note: Skip normal flag as we don't need it.
					if($sFlagCode === 'normal')
					{
						continue;
					}
					$sFormattedFlagCode = str_ireplace('_', '-', $sFlagCode);
					$sFormattedFlagValue = (($aAttrib['attflags'] & $iFlagValue) === $iFlagValue) ? 'true' : 'false';
					$sDataAttributeFlags .= 'data-attribute-flag-'.$sFormattedFlagCode.'="'.$sFormattedFlagValue.'" ';
				}
			}
			// - Value raw
			$sDataValueRaw = isset($aAttrib['value_raw']) ? 'data-value-raw="'.utils::HtmlEntities($aAttrib['value_raw']).'"' : '';

			$sHtml .= "<div class=\"field_container field_{$sLayout}\" $sDataAttributeCode $sDataAttributeType $sDataAttributeLabel $sDataAttributeFlags $sDataValueRaw>\n";
			$sHtml .= "<div class=\"field_label label\">{$aAttrib['label']}</div>\n";

			$sHtml .= "<div class=\"field_data\">\n";
			// By Rom, for csv import, proposed to show several values for column selection
			if (is_array($aAttrib['value']))
			{
				$sHtml .= "<div class=\"field_value\">".implode("</div><div>", $aAttrib['value'])."</div>\n";
			}
			else
			{
				$sHtml .= "<div class=\"field_value\">".$aAttrib['value']."</div>\n";
			}
			// Checking if we should add comments & infos
			$sComment = (isset($aAttrib['comments'])) ? $aAttrib['comments'] : '';
			$sInfo = (isset($aAttrib['infos'])) ? $aAttrib['infos'] : '';
			if ($sComment !== '')
			{
				$sHtml .= "<div class=\"field_comments\">$sComment</div>\n";
			}
			if ($sInfo !== '')
			{
				$sHtml .= "<div class=\"field_infos\">$sInfo</div>\n";
			}
			$sHtml .= "</div>\n";

			$sHtml .= "</div>\n";
		}
		$sHtml .= "</div>\n";

		return $sHtml;
	}

	/**
	 * Build a set of radio buttons suitable for editing a field/attribute of an object (including its validation)
	 *
	 * @param $aAllowedValues array Array of value => display_value
	 * @param $value mixed Current value for the field/attribute
	 * @param $iId mixed Unique Id for the input control in the page
	 * @param $sFieldName string The name of the field, attr_<$sFieldName> will hold the value for the field
	 * @param $bMandatory bool Whether or not the field is mandatory
	 * @param $bVertical bool Disposition of the radio buttons vertical or horizontal
	 * @param $sValidationField string HTML fragment holding the validation field (exclamation icon...)
	 *
	 * @return string The HTML fragment corresponding to the radio buttons
	 */
	public function GetRadioButtons(
		$aAllowedValues, $value, $iId, $sFieldName, $bMandatory, $bVertical, $sValidationField
	) {
		$idx = 0;
		$sHTMLValue = '';
		foreach ($aAllowedValues as $key => $display_value)
		{
			if ((count($aAllowedValues) == 1) && ($bMandatory == 'true'))
			{
				// When there is only once choice, select it by default
				$sSelected = 'checked';
			}
			else
			{
				$sSelected = ($value == $key) ? 'checked' : '';
			}
			$sHTMLValue .= "<input type=\"radio\" id=\"{$iId}_{$key}\" name=\"radio_$sFieldName\" onChange=\"$('#{$iId}').val(this.value).trigger('change');\" value=\"$key\" $sSelected><label class=\"radio\" for=\"{$iId}_{$key}\">&nbsp;$display_value</label>&nbsp;";
			if ($bVertical)
			{
				if ($idx == 0)
				{
					// Validation icon at the end of the first line
					$sHTMLValue .= "&nbsp;{$sValidationField}\n";
				}
				$sHTMLValue .= "<br>\n";
			}
			$idx++;
		}
		$sHTMLValue .= "<input type=\"hidden\" id=\"$iId\" name=\"$sFieldName\" value=\"$value\"/>";
		if (!$bVertical)
		{
			// Validation icon at the end of the line
			$sHTMLValue .= "&nbsp;{$sValidationField}\n";
		}

		return $sHTMLValue;
	}

	/**
	 * Discard unexpected output data (such as PHP warnings)
	 * This is a MUST when the Page output is DATA (download of a document, download CSV export, download ...)
	 */
	public function TrashUnexpectedOutput()
	{
		$this->bTrashUnexpectedOutput = true;
	}

	/**
	 * Read the output buffer and deal with its contents:
	 * - trash unexpected output if the flag has been set
	 * - report unexpected behaviors such as the output buffering being stopped
	 *
	 * Possible improvement: I've noticed that several output buffers are stacked,
	 * if they are not empty, the output will be corrupted. The solution would
	 * consist in unstacking all of them (and concatenate the contents).
	 *
	 * @throws \Exception
	 */
	protected function ob_get_clean_safe()
	{
		$sOutput = ob_get_contents();
		if ($sOutput === false)
		{
			$sMsg = "Design/integration issue: No output buffer. Some piece of code has called ob_get_clean() or ob_end_clean() without calling ob_start()";
			if ($this->bTrashUnexpectedOutput)
			{
				IssueLog::Error($sMsg);
				$sOutput = '';
			}
			else
			{
				$sOutput = $sMsg;
			}
		}
		else
		{
			ob_end_clean(); // on some versions of PHP doing so when the output buffering is stopped can cause a notice
			if ($this->bTrashUnexpectedOutput)
			{
				if (trim($sOutput) != '')
				{
					if (Utils::GetConfig() && Utils::GetConfig()->Get('debug_report_spurious_chars'))
					{
						IssueLog::Error("Trashing unexpected output:'$sOutput'\n");
					}
				}
				$sOutput = '';
			}
		}

		return $sOutput;
	}

	/**
	 * @inheritDoc
	 * @throws \Exception
	 */
	public function output()
	{
		foreach ($this->a_headers as $s_header)
		{
			header($s_header);
		}

		$s_captured_output = $this->ob_get_clean_safe();
		echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n";
		echo "<html>\n";
		echo "<head>\n";
		echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n";
		echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, shrink-to-fit=no\" />";
		echo "<title>".htmlentities($this->s_title, ENT_QUOTES, 'UTF-8')."</title>\n";
		echo $this->get_base_tag();

		// First put stylesheets so they can be loaded before browser interprets JS files, otherwise visual glitch can occur.
		foreach ($this->a_linked_stylesheets as $a_stylesheet)
		{
			if (strpos($a_stylesheet['link'], '?') === false)
			{
				$s_stylesheet = $a_stylesheet['link']."?t=".utils::GetCacheBusterTimestamp();
			}
			else
			{
				$s_stylesheet = $a_stylesheet['link']."&t=".utils::GetCacheBusterTimestamp();
			}
			if ($a_stylesheet['condition'] != "")
			{
				echo "<!--[if {$a_stylesheet['condition']}]>\n";
			}
			echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$s_stylesheet}\" />\n";
			if ($a_stylesheet['condition'] != "")
			{
				echo "<![endif]-->\n";
			}
		}

		// Then inline styles
		if (count($this->a_styles) > 0)
		{
			echo "<style>\n";
			foreach ($this->a_styles as $s_style)
			{
				echo "$s_style\n";
			}
			echo "</style>\n";
		}

		// Favicon
		if (class_exists('MetaModel') && MetaModel::GetConfig())
		{
			echo "<link rel=\"shortcut icon\" href=\"".utils::GetAbsoluteUrlAppRoot()."images/favicon.ico?t=".utils::GetCacheBusterTimestamp()."\" />\n";
		}

		// Dict entries for JS
		if ($this->bAddJSDict)
		{
			$this->output_dict_entries();
		}

		// JS files
		foreach ($this->a_linked_scripts as $s_script)
		{
			// Make sure that the URL to the script contains the application's version number
			// so that the new script do NOT get reloaded from the cache when the application is upgraded
			if (strpos($s_script, '?') === false)
			{
				$s_script .= "?t=".utils::GetCacheBusterTimestamp();
			}
			else
			{
				$s_script .= "&t=".utils::GetCacheBusterTimestamp();
			}
			echo "<script type=\"text/javascript\" src=\"$s_script\"></script>\n";
		}

		// JS inline scripts
		if (count($this->a_scripts) > 0)
		{
			echo "<script type=\"text/javascript\">\n";
			foreach ($this->a_scripts as $s_script)
			{
				echo "$s_script\n";
			}
			echo "</script>\n";
		}

		echo "</head>\n";
		echo "<body>\n";
		echo self::FilterXSS($this->s_content);
		if (trim($s_captured_output) != "")
		{
			echo "<div class=\"raw_output\">".self::FilterXSS($s_captured_output)."</div>\n";
		}
		echo '<div id="at_the_end">'.self::FilterXSS($this->s_deferred_content).'</div>';
		echo "</body>\n";
		echo "</html>\n";

		if (class_exists('DBSearch'))
		{
			DBSearch::RecordQueryTrace();
		}
		if (class_exists('ExecutionKPI'))
		{
			ExecutionKPI::ReportStats();
		}
	}

	/**
	 * Build a series of hidden field[s] from an array
	 *
	 * @param string $sLabel
	 * @param array $aData
	 */
	public function add_input_hidden($sLabel, $aData)
	{
		foreach ($aData as $sKey => $sValue)
		{
			// Note: protection added to protect against the Notice 'array to string conversion' that appeared with PHP 5.4
			// (this function seems unused though!)
			if (is_scalar($sValue))
			{
				$this->add("<input type=\"hidden\" name=\"".$sLabel."[$sKey]\" value=\"$sValue\">");
			}
		}
	}

	protected function get_base_tag()
	{
		$sTag = '';
		if (($this->a_base['href'] != '') || ($this->a_base['target'] != ''))
		{
			$sTag = '<base ';
			if (($this->a_base['href'] != ''))
			{
				$sTag .= "href =\"{$this->a_base['href']}\" ";
			}
			if (($this->a_base['target'] != ''))
			{
				$sTag .= "target =\"{$this->a_base['target']}\" ";
			}
			$sTag .= " />\n";
		}

		return $sTag;
	}

	/**
	 * Get an ID (for any kind of HTML tag) that is guaranteed unique in this page
	 *
	 * @return int The unique ID (in this page)
	 */
	public function GetUniqueId()
	{
		return $this->iNextId++;
	}

	/**
	 * Set the content-type (mime type) for the page's content
	 *
	 * @param $sContentType string
	 *
	 * @return void
	 */
	public function SetContentType($sContentType)
	{
		$this->sContentType = $sContentType;
	}

	/**
	 * Set the content-disposition (mime type) for the page's content
	 *
	 * @param $sDisposition string The disposition: 'inline' or 'attachment'
	 * @param $sFileName string The original name of the file
	 *
	 * @return void
	 */
	public function SetContentDisposition($sDisposition, $sFileName)
	{
		$this->sContentDisposition = $sDisposition;
		$this->sContentFileName = $sFileName;
	}

	/**
	 * Set the transactionId of the current form
	 *
	 * @param $iTransactionId integer
	 *
	 * @return void
	 */
	public function SetTransactionId($iTransactionId)
	{
		$this->iTransactionId = $iTransactionId;
	}

	/**
	 * Returns the transactionId of the current form
	 *
	 * @return integer The current transactionID
	 */
	public function GetTransactionId()
	{
		return $this->iTransactionId;
	}

	public static function FilterXSS($sHTML)
	{
		return str_ireplace('<script', '&lt;script', $sHTML);
	}

	/**
	 * What is the currently selected output format
	 *
	 * @return string The selected output format: html, pdf...
	 */
	public function GetOutputFormat()
	{
		return $this->s_OutputFormat;
	}

	/**
	 * Check whether the desired output format is possible or not
	 *
	 * @param string $sOutputFormat The desired output format: html, pdf...
	 *
	 * @return bool True if the format is Ok, false otherwise
	 */
	function IsOutputFormatAvailable($sOutputFormat)
	{
		$bResult = false;
		switch ($sOutputFormat)
		{
			case 'html':
				$bResult = true; // Always supported
				break;

			case 'pdf':
				$bResult = @is_readable(APPROOT.'lib/MPDF/mpdf.php');
				break;
		}

		return $bResult;
	}

	/**
	 * Check whether the output must be printable (using print.css, for sure!)
	 *
	 * @return bool ...
	 */
	public function IsPrintableVersion()
	{
		return $this->bPrintable;
	}

	/**
	 * Retrieves the value of a named output option for the given format
	 *
	 * @param string $sFormat The format: html or pdf
	 * @param string $sOptionName The name of the option
	 *
	 * @return mixed false if the option was never set or the options's value
	 */
	public function GetOutputOption($sFormat, $sOptionName)
	{
		if (isset($this->a_OutputOptions[$sFormat][$sOptionName]))
		{
			return $this->a_OutputOptions[$sFormat][$sOptionName];
		}

		return false;
	}

	/**
	 * Sets a named output option for the given format
	 *
	 * @param string $sFormat The format for which to set the option: html or pdf
	 * @param string $sOptionName the name of the option
	 * @param mixed $sValue The value of the option
	 */
	public function SetOutputOption($sFormat, $sOptionName, $sValue)
	{
		if (!isset($this->a_OutputOptions[$sFormat]))
		{
			$this->a_OutputOptions[$sFormat] = array($sOptionName => $sValue);
		}
		else
		{
			$this->a_OutputOptions[$sFormat][$sOptionName] = $sValue;
		}
	}

	/**
	 * @param array $aActions
	 * @param array $aFavoriteActions
	 *
	 * @return string
	 */
	public function RenderPopupMenuItems($aActions, $aFavoriteActions = array())
	{
		$sPrevUrl = '';
		$sHtml = '';
		if (!$this->IsPrintableVersion())
		{
			foreach ($aActions as $sActionId => $aAction)
			{
				$sDataActionId = 'data-action-id="'.$sActionId.'"';
				$sClass = isset($aAction['css_classes']) ? 'class="'.implode(' ', $aAction['css_classes']).'"' : '';
				$sOnClick = isset($aAction['onclick']) ? 'onclick="'.htmlspecialchars($aAction['onclick'], ENT_QUOTES,
						"UTF-8").'"' : '';
				$sTarget = isset($aAction['target']) ? "target=\"{$aAction['target']}\"" : "";
				if (empty($aAction['url']))
				{
					if ($sPrevUrl != '') // Don't output consecutively two separators...
					{
						$sHtml .= "<li $sDataActionId>{$aAction['label']}</li>";
					}
					$sPrevUrl = '';
				}
				else
				{
					$sHtml .= "<li $sDataActionId><a $sTarget href=\"{$aAction['url']}\" $sClass $sOnClick>{$aAction['label']}</a></li>";
					$sPrevUrl = $aAction['url'];
				}
			}
			$sHtml .= "</ul></li></ul></div>";
			foreach (array_reverse($aFavoriteActions) as $sActionId => $aAction)
			{
				$sTarget = isset($aAction['target']) ? " target=\"{$aAction['target']}\"" : "";
				$sHtml .= "<div class=\"actions_button\" data-action-id=\"$sActionId\"><a $sTarget href='{$aAction['url']}'>{$aAction['label']}</a></div>";
			}
		}

		return $sHtml;
	}

	/**
	 * @param bool $bReturnOutput
	 *
	 * @throws \Exception
	 */
	protected function output_dict_entries($bReturnOutput = false)
	{
		if ((count($this->a_dict_entries) > 0) || (count($this->a_dict_entries_prefixes) > 0))
		{
			if (class_exists('Dict'))
			{
				// The dictionary may not be available for example during the setup...
				// Create a specific dictionary file and load it as a JS script
				$sSignature = $this->get_dict_signature();
				$sJSFileName = utils::GetCachePath().$sSignature.'.js';
				if (!file_exists($sJSFileName) && is_writable(utils::GetCachePath()))
				{
					file_put_contents($sJSFileName, $this->get_dict_file_content());
				}
				// Load the dictionary as the first javascript file, so that other JS file benefit from the translations
				array_unshift($this->a_linked_scripts,
					utils::GetAbsoluteUrlAppRoot().'pages/ajax.document.php?operation=dict&s='.$sSignature);
			}
		}
	}


	/**
	 * Adds init scripts for the collapsible sections
	 */
	protected function outputCollapsibleSectionInit()
	{
		if (!$this->bHasCollapsibleSection)
		{
			return;
		}

		$this->add_script(<<<'EOD'
function initCollapsibleSection(iSectionId, bOpenedByDefault, sSectionStateStorageKey)
{
var bStoredSectionState = JSON.parse(localStorage.getItem(sSectionStateStorageKey));
var bIsSectionOpenedInitially = (bStoredSectionState == null) ? bOpenedByDefault : bStoredSectionState;

if (bIsSectionOpenedInitially) {
	$("#LnkCollapse_"+iSectionId).toggleClass("open");
	$("#Collapse_"+iSectionId).toggle();
}

$("#LnkCollapse_"+iSectionId).click(function(e) {
	localStorage.setItem(sSectionStateStorageKey, !($("#Collapse_"+iSectionId).is(":visible")));
	$("#LnkCollapse_"+iSectionId).toggleClass("open");
	$("#Collapse_"+iSectionId).slideToggle("normal");
	e.preventDefault(); // we don't want to do anything more (see #1030 : a non wanted tab switching was triggered)
});
}
EOD
		);
	}

	/**
	 * @param string $sSectionLabel
	 * @param bool $bOpenedByDefault
	 * @param string $sSectionStateStorageBusinessKey
	 *
	 * @throws \Exception
	 */
	public function StartCollapsibleSection($sSectionLabel, $bOpenedByDefault = false, $sSectionStateStorageBusinessKey = '')
	{
		$this->add($this->GetStartCollapsibleSection($sSectionLabel, $bOpenedByDefault,	$sSectionStateStorageBusinessKey));
	}

	/**
	 * @param string $sSectionLabel
	 * @param bool $bOpenedByDefault
	 * @param string $sSectionStateStorageBusinessKey
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function GetStartCollapsibleSection($sSectionLabel, $bOpenedByDefault = false, $sSectionStateStorageBusinessKey = '')
	{
		$this->bHasCollapsibleSection = true;
		$sHtml = '';
		static $iSectionId = 0;
		$sHtml .= '<a id="LnkCollapse_'.$iSectionId.'" class="CollapsibleLabel" href="#">'.$sSectionLabel.'</a></br>'."\n";
		$sHtml .= '<div id="Collapse_'.$iSectionId.'" style="display:none">'."\n";

		$oConfig = MetaModel::GetConfig();
		$sSectionStateStorageKey = $oConfig->GetItopInstanceid().'/'.$sSectionStateStorageBusinessKey.'/collapsible-'.$iSectionId;
		$sSectionStateStorageKey = json_encode($sSectionStateStorageKey);
		$sOpenedByDefault = ($bOpenedByDefault) ? 'true' : 'false';
		$this->add_ready_script("initCollapsibleSection($iSectionId, $sOpenedByDefault, '$sSectionStateStorageKey');");

		$iSectionId++;

		return $sHtml;
	}

	public function EndCollapsibleSection()
	{
		$this->add($this->GetEndCollapsibleSection());
	}

	/**
	 * @return string
	 */
	public function GetEndCollapsibleSection()
	{
		return "</div>";
	}

}


interface iTabbedPage
{
	/**
	 * @param string $sTabContainer
	 * @param string $sPrefix
	 *
	 * @return mixed
	 */
	public function AddTabContainer($sTabContainer, $sPrefix = '');

	/**
	 * @param string $sTabContainer
	 * @param string $sTabCode
	 * @param string $sHtml
	 *
	 * @return mixed
	 */
	public function AddToTab($sTabContainer, $sTabCode, $sHtml);

	/**
	 * @param string $sTabContainer
	 *
	 * @return mixed
	 */
	public function SetCurrentTabContainer($sTabContainer = '');

	/**
	 * @param string $sTabCode
	 *
	 * @return mixed
	 */
	public function SetCurrentTab($sTabCode = '');

	/**
	 * Add a tab which content will be loaded asynchronously via the supplied URL
	 *
	 * Limitations:
	 * Cross site scripting is not not allowed for security reasons. Use a normal tab with an IFRAME if you want to
	 * pull content from another server. Static content cannot be added inside such tabs.
	 *
	 * @param string $sTabCode The (localised) label of the tab
	 * @param string $sUrl The URL to load (on the same server)
	 * @param boolean $bCache Whether or not to cache the content of the tab once it has been loaded. flase will cause
	 *     the tab to be reloaded upon each activation.
	 * @param string|null $sTabTitle
	 *
	 * @since 2.0.3
	 */
	public function AddAjaxTab($sTabCode, $sUrl, $bCache = true, $sTabTitle = null);

	public function GetCurrentTab();

	/**
	 * @param string $sTabCode
	 * @param string|null $sTabContainer
	 *
	 * @return mixed
	 */
	public function RemoveTab($sTabCode, $sTabContainer = null);

	/**
	 * Finds the tab whose title matches a given pattern
	 *
	 * @param string $sPattern
	 * @param string|null $sTabContainer
	 *
	 * @return mixed The name of the tab as a string or false if not found
	 */
	public function FindTab($sPattern, $sTabContainer = null);
}

/**
 * Helper class to implement JQueryUI tabs inside a page
 */
class TabManager
{
	const ENUM_TAB_TYPE_HTML = 'html';
	const ENUM_TAB_TYPE_AJAX = 'ajax';

	const DEFAULT_TAB_TYPE = self::ENUM_TAB_TYPE_HTML;

	protected $m_aTabs;
	protected $m_sCurrentTabContainer;
	protected $m_sCurrentTab;

	public function __construct()
	{
		$this->m_aTabs = array();
		$this->m_sCurrentTabContainer = '';
		$this->m_sCurrentTab = '';
	}

	/**
	 * @param string $sTabContainer
	 * @param string $sPrefix
	 *
	 * @return string
	 */
	public function AddTabContainer($sTabContainer, $sPrefix = '')
	{
		$this->m_aTabs[$sTabContainer] = array('prefix' => $sPrefix, 'tabs' => array());

		return "\$Tabs:$sTabContainer\$";
	}

	/**
	 * @param string $sHtml
	 *
	 * @throws \Exception
	 */
	public function AddToCurrentTab($sHtml)
	{
		$this->AddToTab($this->m_sCurrentTabContainer, $this->m_sCurrentTab, $sHtml);
	}

	/**
	 * @return int
	 */
	public function GetCurrentTabLength()
	{
		$iLength = isset($this->m_aTabs[$this->m_sCurrentTabContainer]['tabs'][$this->m_sCurrentTab]['html']) ? strlen($this->m_aTabs[$this->m_sCurrentTabContainer]['tabs'][$this->m_sCurrentTab]['html']) : 0;

		return $iLength;
	}

	/**
	 * Truncates the given tab to the specifed length and returns the truncated part
	 *
	 * @param string $sTabContainer The tab container in which to truncate the tab
	 * @param string $sTab The name/identifier of the tab to truncate
	 * @param integer $iLength The length/offset at which to truncate the tab
	 *
	 * @return string The truncated part
	 */
	public function TruncateTab($sTabContainer, $sTab, $iLength)
	{
		$sResult = substr($this->m_aTabs[$this->m_sCurrentTabContainer]['tabs'][$this->m_sCurrentTab]['html'],
			$iLength);
		$this->m_aTabs[$this->m_sCurrentTabContainer]['tabs'][$this->m_sCurrentTab]['html'] = substr($this->m_aTabs[$this->m_sCurrentTabContainer]['tabs'][$this->m_sCurrentTab]['html'],
			0, $iLength);

		return $sResult;
	}

	/**
	 * @param string $sTabContainer
	 * @param string $sTab
	 *
	 * @return bool
	 */
	public function TabExists($sTabContainer, $sTab)
	{
		return isset($this->m_aTabs[$sTabContainer]['tabs'][$sTab]);
	}

	/**
	 * @return int
	 */
	public function TabsContainerCount()
	{
		return count($this->m_aTabs);
	}

	/**
	 * @param string $sTabContainer
	 * @param string $sTabCode
	 * @param string $sHtml
	 * @param string|null $sTabTitle
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function AddToTab($sTabContainer, $sTabCode, $sHtml, $sTabTitle = null)
	{
		if (!$this->TabExists($sTabContainer, $sTabCode))
		{
			$this->InitTab($sTabContainer, $sTabCode, static::ENUM_TAB_TYPE_HTML, $sTabTitle);
		}

		// If target tab is not of type 'html', throw an exception
		if ($this->m_aTabs[$sTabContainer]['tabs'][$sTabCode]['type'] != static::ENUM_TAB_TYPE_HTML)
		{
			throw new Exception("Cannot add HTML content to the tab '$sTabCode' of type '{$this->m_aTabs[$sTabContainer]['tabs'][$sTabCode]['type']}'");
		}

		// Append to the content of the tab
		$this->m_aTabs[$sTabContainer]['tabs'][$sTabCode]['html'] .= $sHtml;

		return ''; // Nothing to add to the page for now
	}

	/**
	 * @param string $sTabContainer
	 *
	 * @return string
	 */
	public function SetCurrentTabContainer($sTabContainer = '')
	{
		$sPreviousTabContainer = $this->m_sCurrentTabContainer;
		$this->m_sCurrentTabContainer = $sTabContainer;

		return $sPreviousTabContainer;
	}

	/**
	 * @param string $sTabCode
	 *
	 * @return string
	 */
	public function SetCurrentTab($sTabCode = '', $sTabTitle = null)
	{
		$sPreviousTabCode = $this->m_sCurrentTab;
		$this->m_sCurrentTab = $sTabCode;

		// Init tab to HTML tab if not existing
		if (!$this->TabExists($this->GetCurrentTabContainer(), $sTabCode))
		{
			$this->InitTab($this->GetCurrentTabContainer(), $sTabCode, static::ENUM_TAB_TYPE_HTML, $sTabTitle);
		}

		return $sPreviousTabCode;
	}

	/**
	 * Add a tab which content will be loaded asynchronously via the supplied URL
	 *
	 * Limitations:
	 * Cross site scripting is not not allowed for security reasons. Use a normal tab with an IFRAME if you want to
	 * pull content from another server. Static content cannot be added inside such tabs.
	 *
	 * @param string $sTabCode The (localised) label of the tab
	 * @param string $sUrl The URL to load (on the same server)
	 * @param boolean $bCache Whether or not to cache the content of the tab once it has been loaded. false will cause
	 *     the tab to be reloaded upon each activation.
	 *
	 * @return string
	 *
	 * @since 2.0.3
	 */
	public function AddAjaxTab($sTabCode, $sUrl, $bCache = true, $sTabTitle = null)
	{
		// Set the content of the tab
		$this->InitTab($this->m_sCurrentTabContainer, $sTabCode, static::ENUM_TAB_TYPE_AJAX, $sTabTitle);
		$this->m_aTabs[$this->m_sCurrentTabContainer]['tabs'][$sTabCode]['url'] = $sUrl;
		$this->m_aTabs[$this->m_sCurrentTabContainer]['tabs'][$sTabCode]['cache'] = $bCache;

		return ''; // Nothing to add to the page for now
	}

	/**
	 * @return string
	 */
	public function GetCurrentTabContainer()
	{
		return $this->m_sCurrentTabContainer;
	}

	/**
	 * @return string
	 */
	public function GetCurrentTab()
	{
		return $this->m_sCurrentTab;
	}

	/**
	 * @param string $sTabCode
	 * @param string|null $sTabContainer
	 */
	public function RemoveTab($sTabCode, $sTabContainer = null)
	{
		if ($sTabContainer == null)
		{
			$sTabContainer = $this->m_sCurrentTabContainer;
		}
		if (isset($this->m_aTabs[$sTabContainer]['tabs'][$sTabCode]))
		{
			// Delete the content of the tab
			unset($this->m_aTabs[$sTabContainer]['tabs'][$sTabCode]);

			// If we just removed the active tab, let's reset the active tab
			if (($this->m_sCurrentTabContainer == $sTabContainer) && ($this->m_sCurrentTab == $sTabCode))
			{
				$this->m_sCurrentTab = '';
			}
		}
	}

	/**
	 * Finds the tab whose title matches a given pattern
	 *
	 * @param string $sPattern
	 * @param string|null $sTabContainer
	 *
	 * @return mixed The actual name of the tab (as a string) or false if not found
	 */
	public function FindTab($sPattern, $sTabContainer = null)
	{
		$result = false;
		if ($sTabContainer == null)
		{
			$sTabContainer = $this->m_sCurrentTabContainer;
		}
		foreach ($this->m_aTabs[$sTabContainer]['tabs'] as $sTabCode => $void)
		{
			if (preg_match($sPattern, $sTabCode))
			{
				$result = $sTabCode;
				break;
			}
		}

		return $result;
	}

	/**
	 * Make the given tab the active one, as if it were clicked
	 * DOES NOT WORK: apparently in the *old* version of jquery
	 * that we are using this is not supported... TO DO upgrade
	 * the whole jquery bundle...
	 *
	 * @param string $sTabContainer
	 * @param string $sTabCode
	 *
	 * @return string
	 */
	public function SelectTab($sTabContainer, $sTabCode)
	{
		$container_index = 0;
		$tab_index = 0;
		foreach ($this->m_aTabs as $sCurrentTabContainerName => $aTabs)
		{
			if ($sTabContainer == $sCurrentTabContainerName)
			{
				foreach ($aTabs['tabs'] as $sCurrentTabLabel => $void)
				{
					if ($sCurrentTabLabel == $sTabCode)
					{
						break;
					}
					$tab_index++;
				}
				break;
			}
			$container_index++;
		}
		$sSelector = '#tabbedContent_'.$container_index.' > ul';

		return "window.setTimeout(\"$('$sSelector').tabs('select', $tab_index);\", 100);"; // Let the time to the tabs widget to initialize
	}

	/**
	 * @param string $sContent
	 * @param \WebPage $oPage
	 *
	 * @return mixed
	 */
	public function RenderIntoContent($sContent, WebPage $oPage)
	{
		// Render the tabs in the page (if any)
		foreach ($this->m_aTabs as $sTabContainerName => $aTabs)
		{
			$sTabs = '';
			$sPrefix = $aTabs['prefix'];
			$container_index = 0;
			if (count($aTabs['tabs']) > 0)
			{
				// Clean tabs
				foreach ($aTabs['tabs'] as $sTabCode => $aTabData)
				{
					// Sometimes people set an empty tab to force content NOT to be rendered in the previous one. We need to remove them.
					// Note: Look for "->SetCurrentTab('');" for examples.
					if ($sTabCode === '')
					{
						unset($aTabs['tabs'][$sTabCode]);
					}

					// N°3320: Do not display empty tabs
					if (empty($aTabData['html']) && empty($aTabData['url']))
					{
						unset($aTabs['tabs'][$sTabCode]);
					}
				}

				// Render tabs
				if ($oPage->IsPrintableVersion())
				{
					$oPage->add_ready_script(
						<<< EOF
oHiddeableChapters = {};
EOF
					);
					$sTabs = "<!-- tabs -->\n<div id=\"tabbedContent_{$sPrefix}{$container_index}\" class=\"light\">\n";
					$i = 0;
					foreach ($aTabs['tabs'] as $sTabCode => $aTabData)
					{
						$sTabCodeForJs = addslashes($sTabCode);
						$sTabTitleForHtml = utils::HtmlEntities($aTabData['title']);
						$sTabId = "tab_{$sPrefix}{$container_index}$i";
						switch ($aTabData['type'])
						{
							case static::ENUM_TAB_TYPE_AJAX:
								$sTabHtml = '';
								$sUrl = $aTabData['url'];
								$oPage->add_ready_script(
									<<< EOF
$.post('$sUrl', {printable: '1'}, function(data){
	$('#$sTabId > .printable-tab-content').append(data);
});
EOF
								);
								break;

							case static::ENUM_TAB_TYPE_HTML:
							default:
								$sTabHtml = $aTabData['html'];
						}
						$sTabs .= "<div class=\"printable-tab\" id=\"$sTabId\"><h2 class=\"printable-tab-title\">$sTabTitleForHtml</h2><div class=\"printable-tab-content\">".$sTabHtml."</div></div>\n";
						$oPage->add_ready_script(
							<<< EOF
oHiddeableChapters['$sTabId'] = '$sTabTitleForHtml';
EOF
						);
						$i++;
					}
					$sTabs .= "</div>\n<!-- end of tabs-->\n";
				}
				else
				{
					$sTabs = "<!-- tabs -->\n<div id=\"tabbedContent_{$sPrefix}{$container_index}\" class=\"light\">\n";
					$sTabs .= "<ul>\n";
					// Display the unordered list that will be rendered as the tabs
					$i = 0;
					foreach ($aTabs['tabs'] as $sTabCode => $aTabData)
					{
						$sTabCodeForHtml = utils::HtmlEntities($sTabCode);
						$sTabTitleForHtml = utils::HtmlEntities($aTabData['title']);
						switch ($aTabData['type'])
						{
							case static::ENUM_TAB_TYPE_AJAX:
								$sTabs .= "<li data-cache=\"".($aTabData['cache'] ? 'true' : 'false')."\"><a href=\"{$aTabData['url']}\" class=\"tab\" data-tab-id=\"$sTabCodeForHtml\"><span>$sTabTitleForHtml</span></a></li>\n";
								break;

							case static::ENUM_TAB_TYPE_HTML:
							default:
								$sTabs .= "<li><a href=\"#tab_{$sPrefix}{$container_index}$i\" class=\"tab\" data-tab-id=\"$sTabCodeForHtml\"><span>$sTabTitleForHtml</span></a></li>\n";
						}
						$i++;
					}
					$sTabs .= "</ul>\n";
					// Now add the content of the tabs themselves
					$i = 0;
					foreach ($aTabs['tabs'] as $sTabCode => $aTabData)
					{
						switch ($aTabData['type'])
						{
							case static::ENUM_TAB_TYPE_AJAX:
								// Nothing to add
								break;

							case static::ENUM_TAB_TYPE_HTML:
							default:
								$sTabs .= "<div id=\"tab_{$sPrefix}{$container_index}$i\">".$aTabData['html']."</div>\n";
						}
						$i++;
					}
					$sTabs .= "</div>\n<!-- end of tabs-->\n";
				}
			}
			$sContent = str_replace("\$Tabs:$sTabContainerName\$", $sTabs, $sContent);
			$container_index++;
		}

		return $sContent;
	}

	/**
	 * @param string $sTabContainer
	 * @param string $sTabCode
	 * @param string $sTabType
	 * @param string|null $sTabTitle
	 * @since 2.7.0
	 */
	protected function InitTab($sTabContainer, $sTabCode, $sTabType = self::DEFAULT_TAB_TYPE, $sTabTitle = null)
	{
		if (!$this->TabExists($sTabContainer, $sTabCode))
		{
			// Container
			if (!array_key_exists($sTabContainer, $this->m_aTabs))
			{
				$this->m_aTabs[$sTabContainer] = array(
					'prefix' => '',
					'tabs' => array(),
				);
			}

			// Common properties
			$this->m_aTabs[$sTabContainer]['tabs'][$sTabCode] = array(
				'type' => $sTabType,
				'title' => ($sTabTitle !== null) ? Dict::S($sTabTitle) : Dict::S($sTabCode),
			);

			// Specific properties
			switch($sTabType)
			{
				case static::ENUM_TAB_TYPE_AJAX:
					$this->m_aTabs[$sTabContainer]['tabs'][$sTabCode]['url'] = null;
					$this->m_aTabs[$sTabContainer]['tabs'][$sTabCode]['cache'] = null;
					break;

				case static::ENUM_TAB_TYPE_HTML:
				default:
					$this->m_aTabs[$sTabContainer]['tabs'][$sTabCode]['html'] = null;
					break;
			}
		}
	}
}
