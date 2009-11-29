<?php

/* Eregansu: HTML form generation and handling
 *
 * Copyright 2009 Mo McRoberts.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The names of the author(s) of this software may not be used to endorse
 *    or promote products derived from this software without specific prior
 *    written permission.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, 
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY 
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL
 * AUTHORS OF THIS SOFTWARE BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF 
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING 
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS 
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

class Form implements ArrayAccess
{
	protected $fields = array();
	protected $actions = array();
	protected $name = 'form';
	public $action = null;
	public $errorCount = 0;
	public $method = 'POST';
	public $prefix;
	public $notices = array();
	public $errors = array();

	public function __construct($name)
	{
		$this->name = $name;
		$this->prefix = $name . '_';
	}
	
	public function checkSubmission($req)
	{
		$this->action = null;
		$this->errorCount = 0;
		foreach($this->actions as $act)
		{
			if(!empty($req->postData[$this->prefix . $act['name']]))
			{
				$this->action = $act;
				break;
			}
		}
		foreach($this->fields as $k => $f)
		{
			if(isset($req->postData[$this->prefix . $f['name']]))
			{
				$fv = trim($req->postData[$this->prefix . $f['name']]);
				if(strlen($fv))
				{
					$this->fields[$k]['value'] = $fv;
				}
				else if(!empty($f['required']))
				{
					$f['error'] = true;
					$this->errorCount++;
				}
			}
		}
		if($this->errorCount)
		{
			return false;
		}
		return true;
	}
	
	public function field($info)
	{
		if(!isset($info['name']) || !isset($info['type']))
		{
			return;
		}
		$this->fields[$info['name']] = $info;
	}
	
	public function submit($label, $name = 'go')
	{
		$this->actions[] = array('type' => 'submit', 'label' => $label, 'name' => $name);
	}
	
	public function cancel($url, $label = 'go back')
	{
		$this->actions[] = array('type' => 'cancel', 'url' => $url, 'label' => $label);
	}
	
	public function render($req, $multiple = false, $prefix = null)
	{
		$buf = array();
		
		$htmethod = $method = trim(strtoupper($this->method));
		if($method != 'GET' && $method != 'POST')
		{
			$htmethod = 'POST';
		}
		if(!$multiple) $buf[] = '<form method="' . _e($htmethod) . '" action="' . _e($req->uri) . '">';
		if(count($this->notices))
		{
			$buf[] = '<ul class="notices">';
			foreach($this->notices as $notice)
			{
				$buf[] = '<li>' . _e($notice) . '</li>';
			}
			$buf[] = '</ul>';
		}
		if(count($this->errors))
		{
			$buf[] = '<ul class="errors">';
			foreach($this->errors as $error)
			{
				$buf[] = '<li>' . _e($error) . '</li>';
			}
			$buf[] = '</ul>';
		}
		$buf[] = '<input type="hidden" name="__name[]" value="' . _e($this->name) . '" />';
		$buf[] = '<input type="hidden" name="__method" value="' . _e($method) . '" />';
		if(isset($req->session) && isset($req->session->fieldName))
		{
			$buf[] = '<input type="hidden" name="' . _e($req->session->fieldName) . '" value="' . _e($req->session->sid) . '" />';
		}
		foreach($this->fields as $field)
		{
			$this->preprocess($field);
			switch($field['type'])
			{
				case 'hidden':
					$this->renderHidden($buf, $req, $field);
					break;
				case 'textarea':
					$this->renderTextArea($buf, $req, $field);
					break;
				case 'password':
					$this->renderPassword($buf, $req, $field);
					break;
				case 'label':
					$this->renderLabel($buf, $req, $field);
					break;
				case 'select':
					$this->renderSelect($buf, $req, $field);
					break;
				case 'checkbox':
					$this->renderCheckbox($buf, $req, $field);
					break;
				default:
					$this->renderText($buf, $req, $field);
					break;
					
			}
		}
		if(count($this->actions))
		{
			$buf[] = '<fieldset class="actions">';
			$or = false;
			foreach($this->actions as $act)
			{
				if($act['type'] == 'submit')
				{
					$or = true;
					$buf[] = '<input type="submit" name="' . htmlspecialchars($act['name']) . '" value="' . htmlspecialchars($act['label']) . '" />';
				}
				else if($act['type'] == 'cancel')
				{
					if($or)
					{
						$p = ' or ';
						$label = $act['label'];
					}
					else
					{
						$p = '';
						$label = strtoupper(substr($act['label'], 0, 1)) . substr($act['label'], 1);
					}
					$p .= '<a href="' . htmlspecialchars($act['url']) . '">' . htmlspecialchars($label) . '</a>';
					$buf[] = $p;
				}
			}
			$buf[] = '</fieldset>';
		}
		if(!$multiple) $buf[] = '</form>';
		return implode("\n", $buf);
	}
	
	protected function preprocess(&$info)
	{
		if(!isset($info['value']))
		{
			if(isset($info['defaultValue']))
			{
				$info['value'] = $info['defaultValue'];
			}
			else
			{
				$info['value'] = null;
			}
		}
		if(!isset($info['htmlName']))
		{
			$info['htmlName'] = htmlspecialchars($this->prefix . $info['name']);
			if(isset($info['index']))
			{
				$info['htmlName'] .= '[' . htmlspecialchars($info['index']) . ']';
			}
		}
		if(!isset($info['id']))
		{
			$info['id'] = $info['name'];
			if(isset($info['index']))
			{
				$info['id'] .= '-' . $info['index'];
			}
		}
		if(!isset($info['htmlId']))
		{
			$info['htmlId'] = htmlspecialchars($this->name . '-' . $info['id']);
		}
		if(!isset($info['suffix']))
		{
			$info['suffix'] = '';
		}
		if(!isset($info['htmlSuffix']))
		{
			$s = trim($info['suffix']);
			if(strlen($s))
			{
				$info['htmlSuffix'] = ' ' . $s;
			}
			else
			{
				$info['htmlSuffix'] = '';
			}
		}
		
	}
	
	protected function renderVisible(&$buf, $req, $info, $el)
	{
		$class = 'field field-' . $info['type'];
		if(!isset($info['label']))
		{
			$class .= ' unlabelled';
		}
		$buf[] = '<div class="' . $class . '" id="f-' . $info['htmlId'] . '">';
		$pre = $aft = null;
		if(isset($info['label']) && (empty($info['after']) || !empty($info['contains'])))
		{
			$pre = '<label for="' . $info['htmlId'] . '">';
			if(empty($info['after']))
			{
				$pre .= htmlspecialchars($info['label']) . '&nbsp';
			}
			if(empty($info['contains']))
			{
				$pre .= '</label>';
			}
		}
		if(isset($info['label']) && (!empty($info['after']) || !empty($info['contains'])))
		{
			if(empty($info['contains']))
			{
				$aft .= '<label for="' . $info['htmlId'] . '">';
			}
			if(!empty($info['after']))
			{
				$aft .= '&nbsp;' . htmlspecialchars($info['label']);
			}
			$aft .= '</label>';
		}
		$buf[] = $pre . $el . $aft;

		$buf[] = '</div>';
	}
	
	protected function renderHidden(&$buf, $req, $info)
	{
		$buf[] = '<input id="' . $info['htmlId'] . '" type="hidden" name="' . $info['name'] . '" value="' . htmlspecialchars($info['value']) . '"' . $info['htmlSuffix'] . ' />';
	}
	
	protected function renderText(&$buf, $req, $info)
	{
		$this->renderVisible($buf, $req, $info,
			'<input id="' . $info['htmlId'] . '" type="' . $info['type'] . '" name="' . $info['htmlName'] . '" value="' . htmlspecialchars($info['value']) . '"' . $info['htmlSuffix'] . ' />');
	}

	protected function renderCheckbox(&$buf, $req, $info)
	{
		if(!isset($info['checkValue'])) $info['checkValue'] = 1;
		$checked = (strcmp($info['checkValue'], $info['value']) ? '' : ' checked="checked"');
		$this->renderVisible($buf, $req, $info,
			'<input id="' . $info['htmlId'] . '" type="checkbox" name="' . $info['htmlName'] . '" value="' . _e($info['checkValue']) . '"' . $checked . $info['htmlSuffix'] . ' />');
	}

	protected function renderTextArea(&$buf, $req, $info)
	{
		$this->renderVisible($buf, $req, $info,
			'<textarea id="' . $info['htmlId'] . '" type="text" name="' . $info['htmlName'] . '">' . htmlspecialchars($info['value']) . '</textarea>' . $info['htmlSuffix']);
	}

	protected function renderPassword(&$buf, $req, $info)
	{
		$this->renderVisible($buf, $req, $info,
			'<input id="' . $info['htmlId'] . '" type="password" name="' . $info['htmlName'] . '" value="' . htmlspecialchars($info['value']) . '"' . $info['htmlSuffix'] . ' />');
	}

	protected function renderLabel(&$buf, $req, $info)
	{
		$this->renderVisible($buf, $req, $info,
			'<p>' . htmlspecialchars($info['value']) . '</p>');
	}
	
	protected function renderSelect(&$buf, $req, $info)
	{
		$sbuf = array('<select id="' . $info['htmlId'] . '" name="' . $info['htmlName'] . '">');
		if(isset($info['nosel'])) $sbuf[] = '<option value="">' . _e($info['nosel']) . '</option>';
		foreach($info['from'] as $k => $v)
		{
			$s = (!strcmp($k, $info['value']));
			$sbuf[] = '<option ' . ($s?'selected="selected" ':'') . 'value="'.  _e($k) . '">' . _e($v) . '</option>';
		}
		$sbuf[] = '</select>';
		$this->renderVisible($buf, $req, $info, implode("\n", $sbuf));
	}
	
	public function offsetExists($ofs)
	{
		foreach($this->fields as $f)
		{
			if(!strcmp($ofs, $f['name'])) return true;
		}
		foreach($this->actions as $f)
		{
			if(!strcmp($ofs, $f['name'])) return true;
		}
		return false;
	}
	
	public function offsetGet($ofs)
	{
		foreach($this->fields as $f)
		{
			if(!strcmp($ofs, $f['name']))
			{
				if(isset($f['value']) && strlen($f['value'])) return $f['value'];
				if(isset($f['defaultValue']) && strlen($f['defaultValue'])) return $f['defaultValue'];
				return null;
			}
		}
		if(isset($this->action) && !strcmp($this->action['name'], $f))
		{
			return true;
		}
		foreach($this->actions as $f)
		{
			if(!strcmp($ofs, $f['name'])) return false;
		}
		return null;
	}
	
	public function offsetSet($ofs, $value)
	{
		foreach($this->fields as $k => $f)
		{
			if(!strcmp($ofs, $f['name']))
			{
				$this->fields[$k]['defaultValue'] = $value;
				return;
			}
		}	
	}
	
	public function offsetUnset($ofs)
	{
	}

}