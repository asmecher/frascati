{**
 * plugins/generic/frascati/templates/frascatiNMIType.tpl
 *
 * Copyright (c) 2024 Public Knowledge Project
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * Frascati category navigation menu item type edit form part.
 *}
{fbvFormSection id="NMI_TYPE_FRASCATI" class="NMI_TYPE_CUSTOM_EDIT" title="plugins.generic.frascati.navMenuItem.category" for="path" required="true"}
	{fbvElement type="select" id="path" from=$frascatiCategories selected=$path translate=false}
{/fbvFormSection}
