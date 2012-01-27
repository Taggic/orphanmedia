<?php
/**
 * Options for the orphanmedia plugin
 *
 * @author Taggic <taggic@t-online.de>
 */

$conf['ignore_tags']  = array('/\<code\>.*^\<\/code\>/smU',
                              '/\<file\>.*^\<\/file\>/smU',
                              '/\<nowiki\>.*^\<\/nowiki\>/smU',
                              '/%%.*^%%/smU',
                              '/\<html\>.*^\<\/html\>/smU',
                              '/\<php\>.*^\<\/php\>/smU');

