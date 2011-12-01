<?php

/**
 * Feedr - A Simple Syndiction Aggregator for RSS
 *
 * Leverages SimpleXML for RSS XML processing 
 *
 * Copyright (c) 2011, Geoff Doty, and contributors
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are
 * permitted provided that the following conditions are met:
 *
 * 	* Redistributions of source code must retain the above copyright notice, this list of
 * 	  conditions and the following disclaimer.
 *
 * 	* Redistributions in binary form must reproduce the above copyright notice, this list
 * 	  of conditions and the following disclaimer in the documentation and/or other materials
 * 	  provided with the distribution.
 *
 * 	* Neither the name of the SimplePie Team nor the names of its contributors may be used
 * 	  to endorse or promote products derived from this software without specific prior
 * 	  written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS
 * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS
 * AND CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Geoff Doty <n2geoff@gmail.com>
 * @copyright 2011 Geoff Doty. 
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version .45
 * @todo write documentation
 */
class Feedr {
	
	private $feed_url 	   	= NULL;
	private $feed 		   	= NULL;
	private $feed_version 	= NULL;

	private $raw 		   	= NULL;

	private $cache 		   	= FALSE;
	private $cache_dir 		= __DIR__;

	private $errors 		= array();

	public function __construct($feed)
	{
		if(!filter_var($feed, FILTER_VALIDATE_URL))
		{
			if(!file_exists($feed))
			{
                trigger_error('Invalid Location Identifier:  Must be FILE or URL', E_USER_WARNING);
                return $this;
			}
		}

		$this->feed_url = $feed;
		if($this->init())
		{
			return $this;
		}

		return FALSE;
	}

	private function init()
	{
		//suppress xml errors
		libxml_use_internal_errors(TRUE);

		//grab our feed
		$fp = fopen($this->feed_url, 'r');

		if(!$fp)
		{
            trigger_error('Error reading feed stream', E_USER_WARNING);
			$this->errors[] = "Error reading feed stream";
            return $this;
		}
		else
		{
			while(!feof($fp))
			{
				$this->raw .= fread($fp, 8192);
			}
            
            fclose($fp);
		}

		//convert feed to simplexml object
		$this->feed = simplexml_load_string(utf8_encode($this->raw));

		//valid xml?
		if(!$this->feed)
		{
			$errors = lib_get_errors();

			foreach($errors as $error)
			{
				$this->errors[] = $error;
			}
		}

		//initilized succesfully? 
		if(count($this->errors > 0))
		{
			return FALSE;
		}
		else
		{
			return TRUE;
		}
	}

	public function feed_url()
	{
		return $this->feed_url;
	}

	public function feed_version()
	{
		return $this->feed->attributes()->version;
	}

	public function info()
	{
		$info = new Feedr_XML();

		$info->source   = $this->normalize($this->feed_url);
		$info->version  = $this->_normalize($this->feed->attributes()->version);
		$info->size     = strlen($this->raw);

		return $info;
	}

	public function channel()
	{
		return $this->feed->channel;
	}

	public function items($strip = FALSE)
	{
		//return $this->feed->channel->item;
        $items = array();
        
        if($this->_is_okay()) 
        {
            foreach($this->feed->channel->item as $item)
            {
                $itm = new Feedr_XML();
                
                //clean standard RSS elements
                $itm->title 	= $this->_normalize($item->title);
                $itm->link 		= $this->_normalize($item->link);
                $itm->comments 	= $this->_normalize($item->comments);
                $itm->pubDate 	= $this->_normalize($item->pubDate);
                $itm->author 	= $this->_normalize($item->author);
                $itm->guid 		= $this->_normalize($item->guid);
                $itm->source 	= $this->_normalize($item->source);
                $itm->description = $this->_normalize($item->description);
                $itm->category 	= $this->_normalize($item->category);
                
                //get encoded content
                $content 	  = $item->xpath('content:encoded');
                $itm->content = $this->_normalize($content[0]);
                
                //setup enclosure object
                $itm->enclosure = new Feedr_XML();
                
                //get enclosure
                if(isset($item->enclosure))
                {
                    $itm->enclosure->url = $this->_normalize($item->enclosure->attributes()->url);
                    $itm->enclosure->length = $this->_normalize($item->enclosure->attributes()->length);
                    $itm->enclosure->type = $this->_normalize($item->enclosure->attributes()->type);
                }
                
                //lets attached non-standard xml elements
                foreach($item as $key => $value)
                {
                    $standard_items = $this->_standard_rss_items();
                
                    if(!in_array($key, $standard_items))
                    {
                        $itm->$key = $this->_normalize($value);
                    }
                }
                
                $items[] = $itm;
            }
        }   
        return $items;
	}
    
    private function _standard_rss_items()
    {
        return array
        (
            'title',
            'link',
            'description',
            'author',
            'category',
            'comments',
            'enclosure',
            'guid',
            'pubDate',
            'source'
        );
    }
    
    private function _normalize($value)
    {
        return filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    }
    
    private function _is_okay()
    {
        if(count($this->errors) <= 0)
        {
            return TRUE;
        }
        else
        {
            return FALSE;
        }
    }
}

//Dummy Object
class Feedr_XML {
    public function __construct()
    {
        return $this;
    }
    
    public function __get($method)
	{
		return FALSE;
	}
    
    public function __call($method, $arguments)
	{
		return FALSE;
	}
    
    public function __toString()
    {
        return '';
    }
}