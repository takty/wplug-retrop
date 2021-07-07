<?php
namespace wplug\retrop;

/**
 *
 * Utilities for Retrop
 *
 * @author Takuto Yanagida
 * @version 2021-07-08
 *
 */


const FS_FOR_DIGEST = 'for_digest';
const FS_REQUIRED   = 'required';
const FS_TYPE       = 'type';

const FS_TYPE_TITLE         = 'post_title';
const FS_TYPE_CONTENT       = 'post_content';
const FS_TYPE_META          = 'post_meta';
const FS_TYPE_DATE          = 'post_date';
const FS_TYPE_DATE_GMT      = 'post_date_gmt';
const FS_TYPE_SLUG          = 'post_name';
const FS_TYPE_MENU_ORDER    = 'menu_order';
const FS_TYPE_TERM          = 'term';
const FS_TYPE_THUMBNAIL_URL = 'thumbnail_url';
const FS_TYPE_MEDIA         = 'media';
const FS_TYPE_ACF_PM        = 'acf_pm';

const FS_TYPES = [ FS_TYPE_TITLE, FS_TYPE_CONTENT, FS_TYPE_META, FS_TYPE_DATE, FS_TYPE_DATE_GMT, FS_TYPE_SLUG, FS_TYPE_MENU_ORDER, FS_TYPE_TERM, FS_TYPE_THUMBNAIL_URL, FS_TYPE_MEDIA ];

// for FS_TYPE_META
const FS_KEY    = 'key';
const FS_FILTER = 'filter';

const FS_FILTER_CONTENT       = 'post_content';        // for Importer
const FS_FILTER_CONTENT_MEDIA = 'post_content_media';  // for Importer & Exporter
const FS_FILTER_NORM_DATE     = 'norm_date';           // for Importer
const FS_FILTER_NL2BR         = 'nl2br';               // for Importer
const FS_FILTER_MEDIA_URL     = 'media_url';           // for Importer & Exporter
const FS_FILTER_SLUG          = 'post_name';           // for Importer

// for FS_TYPE_TERM
const FS_TAXONOMY  = 'taxonomy';
const FS_AUTO_ADD  = 'auto_add';
const FS_CONV      = 'conv';
const FS_NORM_SLUG = 'norm_slug';
const FS_RAW       = 'raw';


function make_digest( $text ) {
	$text = normalize_key_text( $text );
	$text = str_replace( ' ', '', $text );
	return hash( 'sha224', $text );
}

function normalize_key_text( $text ) {
	$text = strip_tags( trim( $text ) );
	$text = mb_convert_kana( $text, 'rnasKV' );
	$text = mb_strtolower( $text );
	$text = preg_replace( '/[\s!-\/:-@[-`{-~]|[、。，．・：；？！´｀¨＾￣＿―‐／＼～∥｜…‥‘’“”（）〔〕［］｛｝〈〉《》「」『』【】＊※]/u', ' ', $text );
	$text = preg_replace( '/\s(?=\s)/', '', $text );
	$text = trim( $text );
	return $text;
}
