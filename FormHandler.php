<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use App\FormHandlerInterface;
use App\HtmlTag;

class FormHandler extends Model implements FormHandlerInterface
{
	// Used to define output file name - filename (note $name variable) can be altered in render stage
	protected $name;
	// Will hold the html form's content
	protected $output;

	public function __construct($name, $attributes = null)
	{
		$this->name = $name;
		$this->output = array($this->formInitialise($attributes));
	}

	/**
	 * Used by other methods throughout.
	 * Takes an array of attribute and
	 * value pairs and loops through
	 * returning properly formatted
	 * html for methods to add to
	 * $this->output...........
	 */
	private function addAttributes($attributes)
	{
		$attribute_string = '';
		foreach ($attributes as $attr => $value) {
			$attribute_string = $attribute_string . " " . $attr . "=" . "\"$value\"";
		}
		return $attribute_string;
	}

	/**
	 * Used as a form of validation to take
	 * away the need to use either Request
	 * Method Injection or the Validator
	 * Facade. The DB stores a column
	 * with all allowed attributes 
	 * used by other methods.
	 */
	private function getAllowedAttributes($tag)
	{
		$allowed_attributes = HtmlTag::where('name', '=', $tag)->get(['allowed_attributes']);
		$allowed_attributes = explode(',', $allowed_attributes);
		return $allowed_attributes;
	}

	/**
	 * Used as a form of validation similar to the
	 * above. However, this catches cases where
	 * attributes are not entered with tags
	 * A standard set of attributes are
	 * added, but without values...
	 * -configurable by project
	 */
	private function getStandardAttributes($tag)
	{
		$standard_form_attr = HtmlTag::where('name', '=', $tag)->get(['standard_attributes']);
		$standard_form_attr = explode(',', $standard_form_attr);
		$empty_values_array = [];
		for ($i = 0; $i < count($standard_form_attr); $i++) {
			$empty_values_array[] = '';
		}
		$standard_form_attr = array_combine($standard_form_attr, $empty_values_array);
		return $standard_form_attr;
	}

	/**
	 * Called when object is instantiated
	 * and either adds provided attr
	 * or standard set..........
	 */
	public function formInitialise($attributes = null)
	{	
		$form = "<form";
		if ($attributes) {
			$form .= $this->addAttributes($attributes);			
		} else {
			$standard_form_attr = $this->getStandardAttributes('form');
			$form .= $this->addAttributes($standard_form_attr);
		}
		$form .= ">";
		return $form;	
	}
	/**
	 * Allows user to specify an array of html elements. The user
	 * MUST call the method with a specific array format given
	 * as ['tag' => 'p', attr => 'class=\"test another\"]
	 * where key values must be 'tag' and 'attr'...
	 * Dyanmic methods and magic __call used...
	 */
	public function multipleTagBuilder($htmlBuildArray)
	{
		foreach($htmlBuildArray as $index => $html_pair) {
			$this->{$html_pair['tag']}($html_pair['attr']);
		}		
	}

	/**
	 * Takes each form element and
	 * reduces XSS threat using
	 * htmlspecialchars()
	 */
	public function escapeTags($output)
	{
		$safe_output = [];
		foreach ($output as $index => $form_element) {
			$safe_output[] = htmlspecialchars($form_element);
		}
		return $safe_output;
	}

	/**
	 * Assumes user has looked at the
	 * current output array and
	 * then can specify the new
	 * order. User input 3,2,1
	 * would thus reverse the
	 * order of output.....
	 */
	public function formReorder()
	{
		$num_args = func_get_args();
		$output = $this->output;
		if ($num_args !== count($output)) {
			return false;
		}
		$reordered_output = [$output[0]];
		$new_order = func_get_args();		
		for ($i = 0; $i < $num_args; $i++) {
			if ($new_order[$i] == 1 || $new_order[$i] == $num_args-1) {
				continue;
			} else {
				$reordered_output[] = $output[$new_order[$i]-1];
			}
		}
		$reordered_output[] = [$output[$num_args-1]];
		$this->output = $reordered_output;
	}

	/**
	 * Writes to new .txt file
	 * and places in root dir
	 * if path no specified
	 */
	public function render($filename = $this->name, $path = null)
	{
		$path = ($path) ? $path : $_SERVER['DOCUMENT_ROOT'];
		$output = $this->output;
		$output[] = "</form>";
		$output = escapeTags($output);
		$filename = $path . $filename . ".txt";
		$myfile = fopen($filename, "w");
		fwrite($myfile, $output);
	}

	/**
	 * Allows user to access properties
	 * without having to set multiple
	 * getters. Useful to view the 
	 * output and then make 
	 * reorder simpler
	 */
	public function __get($property)
    {
        if (property_exists($this, $property)) {
        return $this->$property;
        }
    }


    /**
	 * Can be used to add single html elements or multiple using
	 * addMultipe method. User simply needs to call $object->
	 * tag(attr) e.g. $object->p(['class' => 'value']) ;
	 * Again, valiation used by checking allowed stored
	 * attributes from DB.
	 */
    public function __call($tag, $attributes = null)
	{
		$html_element = HtmlTag::where('name', '=', $tag)->get();

		if (!$html_element) {
			return false;
		}

		$output = "<" . $tag;

		if (!$attributes) {
			$standard_attributes = $this->getStandardAttributes($html_element->name);
			$output .= $this->addAttributes($standard_attributes);
		} else {
			$allowed_attr = $this->getAllowedAttributes($html_element->name);
			$attr_to_add = [];
			foreach ($attributes as $attr => $value) {
				if (in_array($attr, $allowed_attr)) {
					$attr_to_add = $attr_to_add + [$attr => $value];
				}
				$output .= $this->addAttributes($attr_to_add);
			}
		}		
		$output .= ">";	
		$this->output[] = $output;
	}

}