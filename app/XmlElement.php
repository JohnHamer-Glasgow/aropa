<?php
// rcs_id('$Id: XmlElement.php,v 1.2 2005/02/28 03:51:46 jham005 Exp $');
/**
 * Code for writing XML.
 * @author: Jeff Dairiki
 *
 * FIXME: This code is not (yet) php5 compatible.
 */

//- Changes:
//- John Hamer:- emit <tag .. attr ...> for boolean tags,
//-              instead of <tag ... attr=true ...>
//-              (HTML appears to requires this for INPUT and SELECT tags)

/**
 * A sequence of (zero or more) XmlElements (possibly interspersed with
 * plain strings (CDATA).
 */
class XmlContent
{
    function __construct (/* ... */) {
        $this->_content = array();
        $this->_pushContent_array(func_get_args());
    }

    function pushContent ($arg /*, ...*/) {
        if (func_num_args() > 1)
            $this->_pushContent_array(func_get_args());
        elseif (is_array($arg))
            $this->_pushContent_array($arg);
        else
            $this->_pushContent($arg);
    }

    function _pushContent_array ($array) {
        foreach ($array as $item) {
            if (is_array($item))
                $this->_pushContent_array($item);
            else
                $this->_pushContent($item);
        }
    }

    function _pushContent ($item) {
        if ( is_object($item) && get_class($item) == 'xmlcontent')
            array_splice($this->_content, count($this->_content), 0,
                         $item->_content);
        else
            $this->_content[] = $item;
    }

    function unshiftContent ($arg /*, ...*/) {
        if (func_num_args() > 1)
            $this->_unshiftContent_array(func_get_args());
        elseif (is_array($arg))
            $this->_unshiftContent_array($arg);
        else
            $this->_unshiftContent($arg);
    }

    function _unshiftContent_array ($array) {
        foreach (array_reverse($array) as $item) {
            if (is_array($item))
                $this->_unshiftContent_array($item);
            else
                $this->_unshiftContent($item);
        }
    }

    function _unshiftContent ($item) {
        if (get_class($item) == 'xmlcontent')
            array_splice($this->_content, 0, 0, $item->_content);
        else
            array_unshift($this->_content, $item);
    }
    
    function getContent () {
        return $this->_content;
    }

    function setContent ($arg /* , ... */) {
        $this->_content = array();
        $this->_pushContent_array(func_get_args());
    }

    function printXML () {
        foreach ($this->_content as $item) {
            if (is_object($item)) {
                if (method_exists($item, 'printxml'))
                    $item->printXML();
                elseif (method_exists($item, 'asxml'))
                    echo $item->asXML();
                elseif (method_exists($item, 'asstring'))
                    echo $this->_quote($item->asString());
                else
                    printf("==Object(%s)==", get_class($item));
            }
            else
                echo $this->_quote((string) $item);
        }
    }

    function asXML () {
        $xml = '';
        foreach ($this->_content as $item) {
            if (is_object($item)) {
                if (method_exists($item, 'asxml'))
                    $xml .= $item->asXML();
                elseif (method_exists($item, 'asstring'))
                    $xml .= $this->_quote($item->asString());
                else
                    $xml .= sprintf("==Object(%s)==", get_class($item));
            }
            else
                $xml .= $this->_quote((string) $item);
        }
        return $xml;
    }

    function asPDF () {
        $pdf = '';
        foreach ($this->_content as $item) {
            if (is_object($item)) {
                if (method_exists($item, 'aspdf'))
                    $pdf .= $item->asPDF();
                elseif (method_exists($item, 'asstring'))
                    $pdf .= $this->_quote($item->asString());
                else
                    $pdf .= sprintf("==Object(%s)==", get_class($item));
            }
            else
                $pdf .= $this->_quote((string) $item);
        }
        return $pdf;
    }

    function asString () {
        $val = '';
        foreach ($this->_content as $item) {
            if (is_object($item)) {
                if (method_exists($item, 'asstring'))
                    $val .= $item->asString();
                else
                    $val .= sprintf("==Object(%s)==", get_class($item));
            }
            else
                $val .= (string) $item;
        }
        return trim($val);
    }


    /**
     * See if element is empty.
     *
     * Empty means it has no content.
     * @return bool True if empty.
     */
    function isEmpty () {
        if (empty($this->_content))
            return true;
        foreach ($this->_content as $x) {
            if (is_string($x) ? strlen($x) : !empty($x))
                return false;
        }
        return true;
    }
    
    function _quote ($string) {
      return htmlspecialchars($string);
    }
};

/**
 * An XML element.
 *
 * @param $tagname string Tag of html element.
 */
class XmlElement extends XmlContent
{
    function XmlElement ($tagname /* , $attr_or_content , ...*/) {
        //FIXME: php5 incompatible
        $this->XmlContent();
        $this->_init(func_get_args());
    }

    function _init ($args) {
        if (!is_array($args))
            $args = func_get_args();

        assert(count($args) >= 1);
        //assert(is_string($args[0]));
        $this->_tag = array_shift($args);
        
        if ($args && is_array($args[0]))
            $this->_attr = array_shift($args);
        else {
            $this->_attr = array();
            if ($args && $args[0] === false)
                array_shift($args);
        }

        $this->setContent($args);
    }

    function getTag () {
        return $this->_tag;
    }
    
    function setAttr ($attr, $value = false) {
	if (is_array($attr)) {
            assert($value === false);
            foreach ($attr as $a => $v)
		$this->set($a, $v);
            return;
	}

        assert(is_string($attr));
            
        if ($value === false) {
            unset($this->_attr[$attr]);
        }
        else {
            if (is_bool($value))
                //$value = $attr; //- John Hamer
                $this->_attr[$attr] = $value;
            else
                $this->_attr[$attr] = (string) $value;
        }

	if ($attr == 'class')
	    unset($this->_classes);
    }

    function getAttr ($attr) {
	if ($attr == 'class')
	    $this->_setClasses();

	if (isset($this->_attr[$attr]))
	    return $this->_attr[$attr];
	else
	    return false;
    }

    function _getClasses() {
	if (!isset($this->_classes)) {
	    $this->_classes = array();
	    if (isset($this->_attr['class'])) {
		$classes = explode(' ', (string) $this->_attr['class']);
		foreach ($classes as $class) {
		    $class = trim($class);
		    if ($class)
			$this->_classes[$class] = $class;
		}
	    }
	}
	return $this->_classes;
    }

    function _setClasses() {
	if (isset($this->_classes)) {
	    if ($this->_classes)
		$this->_attr['class'] = join(' ', $this->_classes);
	    else
		unset($this->_attr['class']);
	}
    }

    /**
     * Manipulate the elements CSS class membership.
     *
     * This adds or remove an elements membership
     * in a give CSS class.
     *
     * @param $class string
     *
     * @param $in_class bool
     *   If true (the default) the element is added to class $class.
     *   If false, the element is removed from the class.
     */
    function setInClass($class, $in_class=true) {
	$this->_getClasses();
	$class = trim($class);
	if ($in_class)
	    $this->_classes[$class] = $class;
	else 
	    unset($this->_classes[$class]);
    }

    /**
     * Is element in a given (CSS) class?
     *
     * This checks for the presence of a particular class in the
     * elements 'class' attribute.
     *
     * @param $class string  The class to check for.
     * @return bool True if the element is a member of $class.
     */
    function inClass($class) {
	$this->_parseClasses();
	return isset($this->_classes[trim($class)]);
    }

//-[ John Hamer
//     function startTag() {
//         $start = "<" . $this->_tag;
// 	$this->_setClasses();
//         foreach ($this->_attr as $attr => $val) {
//             if (is_bool($val)) {
//                 if (!$val)
//                     continue;
//                 $val = $attr;
//             }
//             $qval = str_replace("\"", '&quot;', $this->_quote((string)$val));
//             $start .= " $attr=\"$qval\"";
//         }
//         $start .= ">";
//         return $start;
//     }

    function startTag() {
        $start = "<" . $this->_tag;
	$this->_setClasses();
        foreach ($this->_attr as $attr => $val) {
            if (is_bool($val)) {
                if (!$val)
                    continue;
                $start .= " $attr";
            } else {
                $qval = str_replace("\"", '&quot;', $this->_quote((string)$val));
                $start .= " $attr=\"$qval\"";
            }
        }
        $start .= ">";
        return $start;
    }
//-]


    function emptyTag() {
        return substr($this->startTag(), 0, -1) . "/>";
    }

    
    function endTag() {
        return "</$this->_tag>";
    }
    
        
    function printXML () {
        if ($this->isEmpty())
            echo $this->emptyTag();
        else {
            echo $this->startTag();
            // FIXME: The next two lines could be removed for efficiency
            if (!$this->hasInlineContent())
                echo "\n";
            XmlContent::printXML();
            echo "</$this->_tag>";
        }
        if (!$this->isInlineElement())
            echo "\n";
    }

    function asXML () {
        if ($this->isEmpty()) {
            $xml = $this->emptyTag();
        }
        else {
            $xml = $this->startTag();
            // FIXME: The next two lines could be removed for efficiency
            if (!$this->hasInlineContent())
                $xml .= "\n";
            $xml .= XmlContent::asXML();
            $xml .= "</$this->_tag>";
        }
        if (!$this->isInlineElement())
            $xml .= "\n";
        return $xml;
    }

    /**
     * Can this element have inline content?
     *
     * This is a hack, but is probably the best one can do without
     * knowledge of the DTD...
     */
    function hasInlineContent () {
        // This is a hack.
        if (empty($this->_content))
            return true;
        if (is_object($this->_content[0]))
            return false;
        return true;
    }
    
    /**
     * Is this element part of inline content?
     *
     * This is a hack, but is probably the best one can do without
     * knowledge of the DTD...
     */
    function isInlineElement () {
        return false;
    }
    
};

class RawXml {
    function __construct ($xml_text) {
        $this->_xml = $xml_text;
    }

    function printXML () {
        echo $this->_xml;
    }

    function asXML () {
        return $this->_xml;
    }

    function isEmpty () {
        return empty($this->_xml);
    }
}

class FormattedText {
    function __construct ($fs /* , ... */) {
        if ($fs !== false) {
            $this->_init(func_get_args());
        }
    }

    function _init ($args) {
        $this->_fs = array_shift($args);

        // PHP's sprintf doesn't support variable width specifiers,
        // like sprintf("%*s", 10, "x"); --- so we won't either.
        $m = array();
        if (! preg_match_all('/(?<!%)%(\d+)\$/x', $this->_fs, $m)) {
            $this->_args  = $args;
        }
        else {
            // Format string has '%2$s' style argument reordering.
            // PHP doesn't support this.
            if (preg_match('/(?<!%)%[- ]?\d*[^- \d$]/x', $this->_fs)) // $fmt
                // literal variable name substitution only to keep locale
                // strings uncluttered
                trigger_error(sprintf(_("Can't mix '%s' with '%s' type format strings"),
                                      '%1\$s','%s'), E_USER_WARNING);
        
            $this->_fs = preg_replace('/(?<!%)%\d+\$/x', '%', $this->_fs);

            $this->_args = array();
            foreach($m[1] as $argnum) {
                if ($argnum < 1 || $argnum > count($args))
                    trigger_error(sprintf("%s: argument index out of range", 
                                          $argnum), E_USER_WARNING);
                $this->_args[] = $args[$argnum - 1];
            }
        }
    }

    function asXML () {
        // Not all PHP's have vsprintf, so...
        $args[] = XmlElement::_quote((string)$this->_fs);
        foreach ($this->_args as $arg)
            $args[] = AsXML($arg);
        return call_user_func_array('sprintf', $args);
    }

    function printXML () {
        // Not all PHP's have vsprintf, so...
        $args[] = XmlElement::_quote((string)$this->_fs);
        foreach ($this->_args as $arg)
            $args[] = AsXML($arg);
        call_user_func_array('printf', $args);
    }

    function asString() {
        $args[] = $this->_fs;
        foreach ($this->_args as $arg)
            $args[] = AsString($arg);
        return call_user_func_array('sprintf', $args);
    }
}

function PrintXML ($val /* , ... */ ) {
    if (func_num_args() > 1) {
        foreach (func_get_args() as $arg)
            PrintXML($arg);
    }
    elseif (is_object($val)) {
        if (method_exists($val, 'printxml'))
            $val->printXML();
        elseif (method_exists($val, 'asxml')) {
            echo $val->asXML();
        }
        elseif (method_exists($val, 'asstring'))
            echo XmlContent::_quote($val->asString());
        else
            printf("==Object(%s)==", get_class($val));
    }
    elseif (is_array($val)) {
        // DEPRECATED:
        // Use XmlContent objects instead of arrays for collections of XmlElements.
        trigger_error("Passing arrays to PrintXML() is deprecated: (" . AsXML($val, true) . ")",
                      E_USER_NOTICE);
        foreach ($val as $x)
            PrintXML($x);
    }
    else
        echo (string)XmlContent::_quote((string)$val);
}

function AsXML ($val /* , ... */) {
    static $nowarn;

    if (func_num_args() > 1) {
        $xml = '';
        foreach (func_get_args() as $arg)
            $xml .= AsXML($arg);
        return $xml;
    }
    elseif (is_object($val)) {
        if (method_exists($val, 'asxml'))
            return $val->asXML();
        elseif (method_exists($val, 'asstring'))
            return XmlContent::_quote($val->asString());
        else
            return sprintf("==Object(%s)==", get_class($val));
    }
    elseif (is_array($val)) {
        // DEPRECATED:
        // Use XmlContent objects instead of arrays for collections of XmlElements.
        if (empty($nowarn)) {
            $nowarn = true;
            trigger_error("Passing arrays to AsXML() is deprecated: (" . AsXML($val) . ")",
                          E_USER_NOTICE);
            unset($nowarn);
        }
        $xml = '';
        foreach ($val as $x)
            $xml .= AsXML($x);
        return $xml;
    }
    else
        return XmlContent::_quote((string)$val);
}

function AsString ($val) {
    if (func_num_args() > 1) {
        $str = '';
        foreach (func_get_args() as $arg)
            $str .= AsString($arg);
        return $str;
    }
    elseif (is_object($val)) {
        if (method_exists($val, 'asstring'))
            return $val->asString();
        else
            return sprintf("==Object(%s)==", get_class($val));
    }
    elseif (is_array($val)) {
        // DEPRECATED:
        // Use XmlContent objects instead of arrays for collections of XmlElements.
        trigger_error("Passing arrays to AsString() is deprecated", E_USER_NOTICE);
        $str = '';
        foreach ($val as $x)
            $str .= AsString($x);
        return $str;
    }
    
    return (string) $val;
}


function fmt ($fs /* , ... */) {
    $s = new FormattedText(false);

    $args = func_get_args();
    $args[0] = _($args[0]);
    $s->_init($args);
    return $s;
}
