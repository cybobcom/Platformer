<?php

declare(strict_types=1);

namespace Capps\Modules\Core\Classes;

use Capps\Modules\Database\Classes\CBObject;

/**
 * Template Service - replaces eval() calls with safe rendering
 * 
 * File: capps/modules/core/classes/TemplateService.php
 */
class TemplateService
{
	private CBParser $parser;
	
	public function __construct()
	{
		$this->parser = new CBParser();
	}
	
	/**
	 * Render structure template safely - NO eval()!
	 */
	public function renderStructure(CBObject $structure, array $data): string
	{
		$template = $this->loadTemplate($structure->get('template'));
		
		if (empty($template)) {
			return "Template not found: $template";
		}
		
		// Remove easyadmin blocks (from original logic)
		$template = $this->removeEasyAdminBlocks($template);
		
		// Parse CB tags using existing parser
		$template = $this->parser->parse($template, $structure);
		
		// Replace placeholders safely
		$template = $this->replacePlaceholders($template, $data);
		
		// Render content elements
		$contentHtml = $this->renderContentElements($data['content'] ?? [], $data);
		$template = str_replace("###part_content###", $contentHtml, $template);
		
		// Final placeholder replacement
		$template = $this->replaceGlobalPlaceholders($template, $data);
		
		return $template;
	}
	
	/**
	 * Wrap content with template for script routes
	 */
	public function wrapContent(string $content, string $templateType): string
	{
		$templateFile = match($templateType) {
			'admin' => BASEDIR . "data/template/views/mastertemplate_admin_V1.html",
			'main' => BASEDIR . "data/template/views/mastertemplate.html",
			default => null
		};
		
		if (!$templateFile || !file_exists($templateFile)) {
			return $content;
		}
		
		$template = file_get_contents($templateFile);
		$template = str_replace("###part_content###", $content, $template);
		
		return $this->replaceGlobalPlaceholders($template, [
			'random' => time(),
			'baseUrl' => BASEURL,
			'basedir' => BASEDIR
		]);
	}
	
	/**
	 * Load template file safely
	 */
	private function loadTemplate(string $templatePath): string
	{
		if (empty($templatePath)) {
			return "";
		}
		
		$fullPath = BASEDIR . $templatePath;
		
		if (!file_exists($fullPath)) {
			return "";
		}
		
		return file_get_contents($fullPath);
	}
	
	/**
	 * Remove easyadmin blocks - from original core.php
	 */
	private function removeEasyAdminBlocks(string $template): string
	{
		if (str_contains($template, '</cb:easyadmin>')) {
			preg_match_all('/<cb:easyadmin(.*)>(.*)<\/cb:easyadmin>/Us', $template, $matches);
			if (!empty($matches[0])) {
				$template = str_replace($matches[0][0], "", $template);
			}
		}
		
		return $template;
	}
	
	/**
	 * Render content elements safely
	 */
	private function renderContentElements(array $contentElements, array $globalData): string
	{
		$html = '';
		
		foreach ($contentElements as $content) {
			if (!($content instanceof CBObject)) {
				continue;
			}
			
			$elementTemplate = $this->loadTemplate($content->get('template'));
			if (empty($elementTemplate)) {
				continue;
			}
			
			// Remove easyadmin blocks
			$elementTemplate = $this->removeEasyAdminBlocks($elementTemplate);
			
			// Replace content placeholders
			$elementTemplate = parseTemplate($elementTemplate, $content->arrAttributes, "element_|content_", false);
			
			// Parse CB tags
			$elementTemplate = $this->parser->parse($elementTemplate, $globalData['structure'], $content);
			
			// Replace page/structure placeholders if needed
			if (str_contains($elementTemplate, "###page_") || str_contains($elementTemplate, "###structure_")) {
				$elementTemplate = parseTemplate($elementTemplate, $globalData['structure']->arrAttributes, "page_|structure_", false);
			}
			
			$html .= $elementTemplate;
		}
		
		return $html;
	}
	
	/**
	 * Replace placeholders safely
	 */
	private function replacePlaceholders(string $template, array $data): string
	{
		$structure = $data['structure'] ?? null;
		
		if ($structure instanceof CBObject) {
			$template = parseTemplate($template, $structure->arrAttributes, "page_|structure_", false);
		}
		
		return $template;
	}
	
	/**
	 * Replace global placeholders
	 */
	private function replaceGlobalPlaceholders(string $template, array $data): string
	{
		$replacements = [
			'###RANDOM###' => $data['random'] ?? time(),
			'###BASEURL###' => $data['baseUrl'] ?? BASEURL,
			'###BASEDIR###' => $data['basedir'] ?? BASEDIR,
			'###MODULE###' => $data['module'] ?? '',
			'###capps###' => defined('CAPPS') ? CAPPS : '',
			'###CAPPS###' => defined('CAPPS') ? CAPPS : ''
		];
		
		return str_replace(array_keys($replacements), array_values($replacements), $template);
	}
}