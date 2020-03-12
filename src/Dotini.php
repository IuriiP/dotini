<?php

/*
 * Copyright (C) 2017 YuriiP <hard.work.mouse@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Dotini;

/**
 * Dotini allow seting the global constants.
 *
 * @author YuriiP <hard.work.mouse@gmail.com>
 */
class Dotini {

    private $_const = false;
    private $_defined = [];

    /**
     * Create a new dotini instance.
     *
     * @param boolean $define
     */
    public function __construct($define = false) {
        $this->_const = $define;
    }

    /**
     * Special extended syntax:
     * = ${DATA} ; substitited from getenv('DATA')
     * = $[DATA] ; substitited from $_SERVER['DATA']
     * = $(string) ; substitited from this ini var
     * = $</path/to/file> ; substituted from file (.ini)
     *
     * If $ns != false each element define namespaced constant, i.e.
     * foo = 'bar' ; define('FOO','bar');
     * Foo[bar] = 'baz' ; define('Foo\BAR','baz');
     *
     * @param string $filename
     * @param string $ns
     * @return array
     * @throws \ErrorException
     */
    public function load($filename, $ns = '') {
        if (is_dir($filename)) {
            $filename = rtrim($filename, '/\\') . DIRECTORY_SEPARATOR . '.ini';
        }
        if (is_file($filename)) {
            $ini = @parse_ini_file($filename, true, INI_SCANNER_TYPED);
            if (false !== $ini) {
                return $this->_array($ini, $ns, dirname($filename));
            }
            $err = error_get_last();
            throw new \ErrorException("Parse error: " . $err['message']);
        }
        throw new \ErrorException("File '{$filename}' not found.");
    }

    /**
     * Build once
     *
     * @param string $filename
     * @param string $ns
     * @return array
     * @throws \ErrorException
     */
    public static function set($filename, $ns = '', $define = true) {
        $dotini = new Dotini($define);
        return $dotini->load($filename, $ns);
    }

    private function _array($array, $ns, $dirname) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $new = $array[$key] = $this->_array($value, "{$ns}{$key}\\", $dirname);
            } elseif (is_string($value)) {
                $new = $array[$key] = $this->_include($this->_resolve($this->_substitute($value)), "{$ns}{$key}\\", $dirname);
            } else {
                $new = $value;
            }

            if (is_scalar($new)) {
                $this->_defined[$ns . $key] = $new;
                if ($this->_const) {
                    define($ns . strtoupper($key), $new);
                }
            }
        }

        return $array;
    }

    private function _substitute($value) {
        return $this->_convert(preg_replace_callback('/\$\[(.+)\]/' // $_SERVER[]
                                , function ($matches) {
                            $name = $matches[1];
                            return isset($_SERVER[$name]) ? (string) $_SERVER[$name] : '';
                        }, $value));
    }

    private function _resolve($value) {
        return is_string($value) ? $this->_convert(preg_replace_callback('/\$\((.+)\)/' // $this[]
                                , function ($matches) {
                            $name = str_replace('/', '\\', $matches[1]);
                            return isset($this->_defined[$name]) ? $this->_defined[$name] : '';
                        }, $value)) : $value;
    }

    private function _include($value, $ns, $dirname) {
        $matches = [];
        if (is_string($value) && preg_match('/^\$\<(.+)\>$/', $value, $matches)) {
            $filename = $matches[1] . '.ini';
            return $this->load(false === strpos('/\\', $filename[0]) ? "{$dirname}/{$filename}" : $filename, $ns);
        } else {
            return $value;
        }
    }

    private function _convert($value) {
        if (!is_numeric($value)) {
            return $value;
        }
        return (float) $value !== (int) $value ? floatval($value) : intval($value);
    }

}
