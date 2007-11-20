<?php

/**
 * Texy! - web text markup-language (for PHP 4)
 * --------------------------------------------
 *
 * Copyright (c) 2004, 2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the GNU GPL license that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://texy.info/
 *
 * @copyright  Copyright (c) 2004, 2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @link       http://texy.info/
 * @package    Texy
 */



/**
 * Images module
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2007 David Grudl
 * @package    Texy
 * @version    $Revision$ $Date$
 */
class TexyImageModule extends TexyModule
{
    /** @var string  root of relative images (http) */
    var $root = 'images/';

    /** @var string  root of linked images (http) */
    var $linkedRoot = 'images/';

    /** @var string  physical location of images on server */
    var $fileRoot = NULL;

    /** @var string  left-floated images CSS class */
    var $leftClass;

    /** @var string  right-floated images CSS class */
    var $rightClass;

    /** @var string  default alternative text */
    var $defaultAlt = '';

    /** @var string  images onload handler */
    var $onLoad = "var i=new Image();i.src='%i';if(typeof preload=='undefined')preload=new Array();preload[preload.length]=i;this.onload=''";

    /** @var array image references */
    var $references = array();




    function __construct($texy)
    {
        $this->texy = $texy;

        $texy->allowed['image/definition'] = TRUE;
        $texy->addHandler('image', array($this, 'solve'));
        $texy->addHandler('beforeParse', array($this, 'beforeParse'));

        // [*image*]:LINK
        $texy->registerLinePattern(
            array($this, 'patternImage'),
            '#'.TEXY_IMAGE.TEXY_LINK_N.'??()#Uu',
            'image'
        );
    }



    /**
     * Text pre-processing
     * @param Texy
     * @param string
     * @return void
     */
    function beforeParse($texy, & $text)
    {
        if ($texy->allowed['image/definition']) {
            // [*image*]: urls .(title)[class]{style}
           $text = preg_replace_callback(
               '#^\[\*([^\n]+)\*\]:\ +(.+)\ *'.TEXY_MODIFIER.'?\s*()$#mUu',
               array($this, 'patternReferenceDef'),
               $text
           );
        }
    }



    /**
     * Callback for: [*image*]: urls .(title)[class]{style}
     *
     * @param array      regexp matches
     * @return string
     */
    function patternReferenceDef($matches)
    {
        list(, $mRef, $mURLs, $mMod) = $matches;
        //    [1] => [* (reference) *]
        //    [2] => urls
        //    [3] => .(title)[class]{style}<>

        $image = $this->factoryImage($mURLs, $mMod, FALSE);
        $this->addReference($mRef, $image);
        return '';
    }



    /**
     * Callback for [* small.jpg 80x13 | small-over.jpg | big.jpg .(alternative text)[class]{style}>]:LINK
     *
     * @param TexyLineParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    function patternImage($parser, $matches)
    {
        list(, $mURLs, $mMod, $mAlign, $mLink) = $matches;
        //    [1] => URLs
        //    [2] => .(title)[class]{style}<>
        //    [3] => * < >
        //    [4] => url | [ref] | [*image*]

        $tx = $this->texy;

        $image = $this->factoryImage($mURLs, $mMod.$mAlign);

        if ($mLink) {
            if ($mLink === ':') {
                $link = new TexyLink($image->linkedURL === NULL ? $image->URL : $image->linkedURL);
                $link->raw = ':';
                $link->type = TEXY_LINK_IMAGE;
            } else {
                $link = $tx->linkModule->factoryLink($mLink, NULL, NULL);
            }
        } else $link = NULL;

        return $tx->invokeAroundHandlers('image', $parser, array($image, $link));
    }



    /**
     * Adds new named reference to image
     *
     * @param string  reference name
     * @param TexyImage
     * @return void
     */
    function addReference($name, /*TexyImage*/ $image)
    {
        $image->name = TexyUtf::strtolower($name);
        $this->references[$image->name] = $image;
    }



    /**
     * Returns named reference
     *
     * @param string  reference name
     * @return TexyImage  reference descriptor (or FALSE)
     */
    function getReference($name)
    {
        $name = TexyUtf::strtolower($name);
        if (isset($this->references[$name]))
            return clone ($this->references[$name]);

        return FALSE;
    }



    /**
     * Parses image's syntax
     * @param string  input: small.jpg 80x13 | small-over.jpg | linked.jpg
     * @param string
     * @param bool
     * @return TexyImage
     */
    function factoryImage($content, $mod, $tryRef = TRUE)
    {
        $image = $tryRef ? $this->getReference(trim($content)) : FALSE;

        if (!$image) {
            $tx = $this->texy;
            $content = explode('|', $content);
            $image = new TexyImage;

            // dimensions
            $matches = NULL;
            if (preg_match('#^(.*) (\d+|\?) *(X|x) *(\d+|\?) *()$#U', $content[0], $matches)) {
                $image->URL = trim($matches[1]);
                $image->asMax = $matches[3] === 'X';
                $image->width = $matches[2] === '?' ? NULL : (int) $matches[2];
                $image->height = $matches[4] === '?' ? NULL : (int) $matches[4];
            } else {
                $image->URL = trim($content[0]);
            }

            if (!$tx->checkURL($image->URL, 'i')) $image->URL = NULL;

            // onmouseover image
            if (isset($content[1])) {
                $tmp = trim($content[1]);
                if ($tmp !== '' && $tx->checkURL($tmp, 'i')) $image->overURL = $tmp;
            }

            // linked image
            if (isset($content[2])) {
                $tmp = trim($content[2]);
                if ($tmp !== '' && $tx->checkURL($tmp, 'a')) $image->linkedURL = $tmp;
            }
        }

        $image->modifier->setProperties($mod);
        return $image;
    }



    /**
     * Finish invocation
     *
     * @param TexyHandlerInvocation  handler invocation
     * @param TexyImage
     * @param TexyLink
     * @return TexyHtml|FALSE
     */
    function solve($invocation, /*TexyImage*/ $image, $link)
    {
        if ($image->URL == NULL) return FALSE;

        $tx = $this->texy;

        $mod = $image->modifier;
        $alt = $mod->title;
        $mod->title = NULL;
        $hAlign = $mod->hAlign;
        $mod->hAlign = NULL;

        $el = TexyHtml::el('img');
        $el->attrs['src'] = NULL; // trick - move to front
        $mod->decorate($tx, $el);
        $el->attrs['src'] = Texy::prependRoot($image->URL, $this->root);
        if (!isset($el->attrs['alt'])) {
            if ($alt !== NULL) $el->attrs['alt'] = $tx->typographyModule->postLine($alt);
            else $el->attrs['alt'] = $this->defaultAlt;
        }

        if ($hAlign) {
            $var = $hAlign . 'Class'; // leftClass, rightClass
            if (!empty($this->$var)) {
                $el->attrs['class'][] = $this->$var;

            } elseif (empty($tx->alignClasses[$hAlign])) {
                $el->attrs['style']['float'] = $hAlign;

            } else {
                $el->attrs['class'][] = $tx->alignClasses[$hAlign];
            }
        }

        if (!is_int($image->width) || !is_int($image->height) || $image->asMax) {
            // autodetect fileRoot
            if ($this->fileRoot === NULL && isset($_SERVER['SCRIPT_FILENAME'])) {
                $this->fileRoot = dirname($_SERVER['SCRIPT_FILENAME']) . '/' . $this->root;
            }

            // detect dimensions
            // absolute URL & security check for double dot
            if (Texy::isRelative($image->URL) && strpos($image->URL, '..') === FALSE) {
                $file = rtrim($this->fileRoot, '/\\') . '/' . $image->URL;
                if (is_file($file)) {
                    $size = getImageSize($file);
                    if (is_array($size)) {
                        if ($image->asMax) {
                            $ratio = 1;
                            if (is_int($image->width)) $ratio = min($ratio, $image->width / $size[0]);
                            if (is_int($image->height)) $ratio = min($ratio, $image->height / $size[1]);
                            $image->width = round($ratio * $size[0]);
                            $image->height = round($ratio * $size[1]);

                        } elseif (is_int($image->width)) {
                            $ratio = round($size[1] / $size[0] * $image->width);
                            $image->height = round($size[1] / $size[0] * $image->width);

                        } elseif (is_int($image->height)) {
                            $image->width = round($size[0] / $size[1] * $image->height);

                        } else {
                            $image->width = $size[0];
                            $image->height = $size[1];
                        }
                    }
                }
            }
        }

        $el->attrs['width'] = $image->width;
        $el->attrs['height'] = $image->height;

        // onmouseover actions generate
        if ($image->overURL !== NULL) {
            $overSrc = Texy::prependRoot($image->overURL, $this->root);
            $el->attrs['onmouseover'] = 'this.src=\'' . addSlashes($overSrc) . '\'';
            $el->attrs['onmouseout'] = 'this.src=\'' . addSlashes($el->attrs['src']) . '\'';
            $el->attrs['onload'] = str_replace('%i', addSlashes($overSrc), $this->onLoad);
            $tx->summary['preload'][] = $overSrc;
        }

        $tx->summary['images'][] = $el->attrs['src'];

        if ($link) return $tx->linkModule->solve(NULL, $link, $el);

        return $el;
    }

}







/**
 * @package Texy
 */
class TexyImage extends NObject4
{
    /** @var string  base image URL */
    var $URL;

    /** @var string  on-mouse-over image URL */
    var $overURL;

    /** @var string  anchored image URL */
    var $linkedURL;

    /** @var int  optional image width */
    var $width;

    /** @var int  optional image height */
    var $height;

    /** @var bool  image width and height are maximal */
    var $asMax;

    /** @var TexyModifier */
    var $modifier;

    /** @var string  reference name (if is stored as reference) */
    var $name;



    function __construct()
    {
        $this->modifier = new TexyModifier;
    }



    function __clone()
    {
        if ($this->modifier) {
            $this->modifier = clone ($this->modifier);
        }
    }

}
