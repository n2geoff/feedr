<?php

/**
 * Feedr - A Simple Syndiction Aggregator for RSS
 *
 * Leverages SimpleXML for RSS XML processing 
 *
 * Copyright (c) 2012, Geoff Doty
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are
 * permitted provided that the following conditions are met:
 *
 *  * Redistributions of source code must retain the above copyright notice, this list of
 *    conditions and the following disclaimer.
 *
 *  * Redistributions in binary form must reproduce the above copyright notice, this list
 *    of conditions and the following disclaimer in the documentation and/or other materials
 *    provided with the distribution.
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
 * @copyright 2012 Geoff Doty. 
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @version 0.5.1
 * @todo write documentation
 */
class Feedr {
    
    private $request        = NULL;        //request url
    private $feed           = NULL;        //rss feed object
    private $response       = NULL;        //raw response

    private $cache          = FALSE;       //unused
    private $cache_dir      = __DIR__;     //unused

    private $errors         = array();     //error collection

    public function __construct($request)
    {
        if(!filter_var($request, FILTER_VALIDATE_URL))
        {
            if(!file_exists($request))
            {
                trigger_error('Invalid Location Identifier:  Must be FILE or URL', E_USER_WARNING);
                return $this;
            }
        }

        //set feed url
        $this->request = $request;
        
        //initilize feed
        if($this->init())
        {
            return $this;
        }
    }

    /**
     * Initilizes the Feed
     *
     * Fetches and validates xml content
     *
     * @return bool
     */
    private function init()
    {
        if(function_exists('curl_init'))
        {
            $ch = curl_init($this->request);

            //define cURL options
            $options = array
            (
                CURLOPT_HEADER => 0,          //do not return headers
                CURLOPT_FOLLOWLOCATION => 1,  //redirect as needed
                CURLOPT_RETURNTRANSFER => 1   //return content
            );

            //apply curl options
            curl_setopt_array($ch, $options);

            //execute curl request
            $this->response = curl_exec($ch);

            //close curl connection
            curl_close($ch);
        }
        elseif(ini_get('allow_url_fopen'))  
        {
            $fp = fopen($this->request, 'r');

            if($fp)
            {
                while(!feof($fp))
                {
                    $this->response .= fread($fp, 8192);
                }
                
                fclose($fp);
            }
        }
        else
        {
            trigger_error('Error reading feed stream', E_USER_WARNING);
            $this->errors[] = "Error reading feed stream";
            return $this;
        }

        //suppress xml errors
        libxml_use_internal_errors(TRUE);

        //convert feed to simplexml object
        if($this->feed = simplexml_load_string(utf8_encode($this->response)))
        {
            return TRUE;
        }
        else 
        {
            //any errors generated
            if(!$this->feed)
            {
                $errors = libxml_get_errors();

                foreach($errors as $error)
                {
                    $this->errors[] = $error;
                }
            }

            return FALSE;
        }
    }

    public function feed_url()
    {
        return $this->request;
    }

    public function feed_version()
    {
        return $this->feed->attributes()->version;
    }

    public function info()
    {
        $info = new Feedr_XML();

        $info->source   = $this->normalize($this->request);
        $info->version  = $this->_normalize($this->feed->attributes()->version);
        $info->size     = strlen($this->response);

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
                $itm->title     = $this->_normalize($item->title);
                $itm->link      = $this->_normalize($item->link);
                $itm->comments  = $this->_normalize($item->comments);
                $itm->pubDate   = $this->_normalize($item->pubDate);
                $itm->author    = $this->_normalize($item->author);
                $itm->guid      = $this->_normalize($item->guid);
                $itm->source    = $this->_normalize($item->source);
                $itm->description = $this->_normalize($item->description);
                $itm->category  = $this->_normalize($item->category);
                
                //get encoded content
                $content      = $item->xpath('content:encoded');
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
    
    /**
     * @todo future strict validation
     */
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
    
    /**
     * Cleans Feed Values
     *
     * Essentially this allows one place to 
     * tweak returned values
     *
     * @param string $value
     * @return string
     */
    private function _normalize($value)
    {
        return filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    }
    
    /**
     * Checks error array
     *
     * @return bool
     */
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