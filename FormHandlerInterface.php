<?php namespace App;

interface FormHandlerInterface
{
	private function addAttributes($attributes);

	private function getAllowedAttributes($tag);

	private function getStandardAttributes($tag);

	private function formInitialise($attributes);

	public function render($filename);

	private function escapeTags($output);

	public function formReorder($output);

	public function multipleTagBuilder($htmlBuildArray);
}