<?php
/**
 * Translation Entry Stub
 * 
 * Required by translations.php
 */

if (!class_exists('Translation_Entry')) {
    class Translation_Entry {
        public $singular = '';
        public $plural = '';
        public $translations = [];
        public $context = null;
        public $translator_comments = '';
        public $extracted_comments = '';
        public $references = [];
        public $flags = [];
        
        public function __construct($args = []) {
            if (is_array($args)) {
                foreach ($args as $key => $value) {
                    $this->$key = $value;
                }
            }
        }
        
        public function key() {
            return $this->context ? $this->context . chr(4) . $this->singular : $this->singular;
        }
    }
}
