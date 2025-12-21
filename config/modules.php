<?php
/**
 * Module Registry
 *
 * @package Cotex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'lms-engine'      => [
		'name'        => 'LMS Engine',
		'slug'        => 'lms-engine',
		'description' => 'Full LMS: courses, users, progress, certification',
		'class'       => 'Cotex\\Modules\\LMS_Engine\\Module',
		'path'        => 'modules/lms-engine',
	],
	'quiz-engine'     => [
		'name'        => 'Quiz Engine',
		'slug'        => 'quiz-engine',
		'description' => 'Advanced quiz system (works standalone + LMS)',
		'class'       => 'Cotex\\Modules\\Quiz_Engine\\Module',
		'path'        => 'modules/quiz-engine',
	],
	'code-blocks'     => [
		'name'        => 'Code Blocks',
		'slug'        => 'code-blocks',
		'description' => 'High-performance syntax highlighted code blocks',
		'class'       => 'Cotex\\Modules\\Code_Blocks\\Module',
		'path'        => 'modules/code-blocks',
	],
	'markdown-studio' => [
		'name'        => 'Markdown Studio',
		'slug'        => 'markdown-studio',
		'description' => 'Markdown authoring + rendering engine',
		'class'       => 'Cotex\\Modules\\Markdown_Studio\\Module',
		'path'        => 'modules/markdown-studio',
	],
];
