<?php
namespace st;

/**
 *
 * Bimeson (Taxonomy)
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-10-23
 *
 */


class Bimeson_Taxonomy {

	const DEFAULT_TAXONOMY     = 'bm_cat';
	const DEFAULT_SUB_TAX_BASE = 'bm_cat_';

	const KEY_LAST_CAT_OMITTED = '_bimeson_last_cat_omitted';
	const KEY_IS_HIDDEN        = '_bimeson_is_hidden';

	const CLS_FILTER_SELECT = 'bimeson-filter-select';
	const KEY_YEAR          = '_year';
	const VAL_YEAR_ALL      = 'all';
	const QVAR_YEAR         = 'bm-year';
	const DS_KEY_YEAR       = 'year';

	private $_post_type;
	private $_labels;
	private $_tax_root;
	private $_tax_sub_base;
	private $_year_formatter = null;

	private $_old_taxonomy = [];
	private $_old_terms = [];

	private $_root_terms = false;
	private $_sub_tax_to_terms = [];

	public function __construct( $post_type, $labels, $taxonomy = false, $sub_tax_base = false ) {
		$this->_post_type    = $post_type;
		$this->_labels       = $labels;
		$this->_tax_root     = ( $taxonomy === false ) ? self::DEFAULT_TAXONOMY : $taxonomy;
		$this->_tax_sub_base = ( $sub_tax_base === false ) ? self::DEFAULT_SUB_TAX_BASE : $sub_tax_base;

		register_taxonomy( $this->_tax_root, $this->_post_type, [
			'hierarchical'       => true,
			'label'              => $this->_labels['taxonomy'],
			'public'             => false,
			'show_ui'            => true,
			'show_in_quick_edit' => false,
			'meta_box_cb'        => false,
			'rewrite'            => false,
		] );
		register_taxonomy_for_object_type( $this->_tax_root, $this->_post_type );
		\st\ordered_term\make_terms_ordered( [ $this->_tax_root ] );
		$this->_register_sub_tax_all();

		add_action( "{$this->_tax_root}_edit_form_fields", [ $this, '_cb_taxonomy_edit_form_fields' ], 10, 2 );
		add_action( "edit_terms",                          [ $this, '_cb_edit_taxonomy' ], 10, 2 );
		add_action( "edited_{$this->_tax_root}",           [ $this, '_cb_edited_taxonomy' ], 10, 2 );
		add_filter( 'query_vars',                          [ $this, '_cb_query_vars' ] );

		foreach ( $this->get_sub_taxonomies() as $sub_tax ) {
			add_action( "{$sub_tax}_edit_form_fields", [ $this, '_cb_taxonomy_edit_form_fields' ], 10, 2 );
			add_action( "edited_{$sub_tax}",           [ $this, '_cb_edited_taxonomy' ], 10, 2 );
		}
	}

	public function set_year_formatter( $func ) {
		$this->_year_formatter = $func;
	}

	private function _get_root_terms() {
		if ( $this->_root_terms ) return $this->_root_terms;
		$this->_root_terms = get_terms( $this->_tax_root, [ 'hide_empty' => 0 ] );
		return $this->_root_terms;
	}

	private function _get_sub_terms( $sub_tax ) {
		if ( $this->_sub_tax_to_terms[ $sub_tax ] !== false ) return $this->_sub_tax_to_terms[ $sub_tax ];
		$this->_sub_tax_to_terms[ $sub_tax ] = get_terms( $sub_tax, [ 'hide_empty' => 0 ] );
		return $this->_sub_tax_to_terms[ $sub_tax ];
	}

	private function _register_sub_tax_all() {
		$roots = $this->_get_root_terms();
		$sub_taxes = [];
		foreach ( $roots as $r ) {
			$sub_tax = $this->term_to_taxonomy( $r );
			$sub_taxes[] = $sub_tax;
			$this->register_sub_tax( $sub_tax, $r->name );
		}
		\st\ordered_term\make_terms_ordered( $sub_taxes );
	}

	private function _get_query_var_name( $slug ) {
		return str_replace( '_', '-', "{$this->_tax_sub_base}{$slug}" );
	}

	public function register_sub_tax( $tax, $name ) {
		register_taxonomy( $tax, $this->_post_type, [
			'hierarchical'       => true,
			'label'              => "{$this->_labels['taxonomy']} ($name)",
			'public'             => true,
			'show_ui'            => true,
			'rewrite'            => false,
			'sort'               => true,
			'show_admin_column'  => false,
			'show_in_quick_edit' => false,
			'meta_box_cb'        => false
		] );
		$this->_root_terms = false;
		$this->_sub_tax_to_terms[ $tax ] = false;
	}

	public function get_taxonomy() {
		return $this->_tax_root;
	}

	public function term_to_taxonomy( $term ) {
		$slug = '';
		if ( is_string( $term ) ) {
			$slug = $term;
		} else {
			$slug = $term->slug;
		}
		$slug = str_replace( '-', '_', $slug );
		return $this->_tax_sub_base . $slug;
	}

	public function sub_term_to_id( $root_slug, $sub_term ) {
		$sub_tax = $this->term_to_taxonomy( $root_slug );
		return str_replace( '_', '-', "{$sub_tax}-{$sub_term->slug}" );
	}

	public function get_root_slugs() {
		$roots = $this->_get_root_terms();
		return array_map( function ( $e ) { return $e->slug; }, $roots );
	}

	public function get_sub_taxonomies() {
		$rss = $this->get_root_slugs();
		$slugs = [];
		foreach( $rss as $rs ) $slugs[ $rs ] = $this->term_to_taxonomy( $rs );
		return $slugs;
	}

	public function get_root_slugs_to_sub_slugs( $do_omit_first = false, $do_hide = false ) {
		$roots = $this->_get_root_terms();
		$slugs = [];
		foreach ( $roots as $idx => $r ) {
			if ( $do_omit_first && $idx === 0 ) continue;
			if ( $do_hide ) {
				$val_hide = get_term_meta( $r->term_id, self::KEY_IS_HIDDEN, true );
				if ( $val_hide ) continue;
			}
			$sub_tax = $this->term_to_taxonomy( $r );
			$terms = $this->_get_sub_terms( $sub_tax );
			$slugs[ $r->slug ] = array_map( function ( $e ) { return $e->slug; }, $terms );;
		}
		return $slugs;
	}

	public function get_root_slugs_to_sub_terms( $do_omit_first = false, $do_hide = false ) {
		$roots = $this->_get_root_terms();
		$terms = [];
		foreach( $roots as $idx => $r ) {
			if ( $do_omit_first && $idx === 0 ) continue;
			if ( $do_hide ) {
				$val_hide = get_term_meta( $r->term_id, self::KEY_IS_HIDDEN, true );
				if ( $val_hide ) continue;
			}
			$sub_tax = $this->term_to_taxonomy( $r );
			$terms[ $r->slug ] = $this->_get_sub_terms( $sub_tax );
		}
		return $terms;
	}

	public function the_filter( $filter_state = false, $year_start = false, $year_end = false, $years_exist = [] ) {
		$slug_to_terms = $this->get_root_slugs_to_sub_terms( false, true );

		if ( is_admin() ) {
			global $post;
			$state = $this->get_filter_state_from_meta( $post );
		} else {
			$state = $this->get_filter_state_from_qvar();
		}
		if ( ! empty( $years_exist ) ) $this->_echo_year_select( $years_exist, $state );

		foreach ( $slug_to_terms as $slug => $terms ) {
			$fsset = isset( $filter_state[ $slug ] );
			if ( ! $fsset || 1 < count( $filter_state[ $slug ] ) ) {
				$this->_echo_tax_checkboxes( $slug, $terms, $state, $fsset ? $filter_state[ $slug ] : false );
			}
		}
	}

	private function _echo_year_select( $years, $state ) {
		$val = $state[ self::KEY_YEAR ];
	?>
		<div class="bimeson-filter-key" data-key="<?php echo self::KEY_YEAR ?>">
			<div class="bimeson-filter-key-inner">
				<select name="<?php echo self::KEY_YEAR ?>" class="<?php echo self::CLS_FILTER_SELECT ?>">
					<option value="<?php echo self::VAL_YEAR_ALL ?>"><?php esc_html_e( $this->_labels['year_selector'] ) ?></option>
	<?php
		foreach ( $years as $y ) {
			if ( is_callable( $this->_year_formatter ) ) {
				$func = $this->_year_formatter;
				$_name = esc_html( $func( (int) $y ) );
			} else if ( class_exists( '\st\Multilang' ) ) {
				$date = date_create_from_format( 'Y', $y );
				$_name = esc_html( date_format( $date, \st\Multilang::get_instance()->get_date_format( 'year' ) ) );
			} else {
				$_name = esc_html( $y );
			}
			echo "<option value=\"$y\"" . ( ( (int) $y === (int) $val ) ? ' selected' : '' ) . ">$_name</option>";
		}
	?>
				</select>
			</div>
		</div>
	<?php
	}

	private function _echo_tax_checkboxes( $root_slug, $terms, $state, $filtered ) {
		$t = get_term_by( 'slug', $root_slug, $this->_tax_root );
		if ( class_exists( '\st\Multilang' ) ) {
			$_cat_name = esc_html( \st\Multilang::get_instance()->get_term_name( $t ) );
		} else {
			$_cat_name = esc_html( $t->name );
		}
		$_slug = esc_attr( $root_slug );
		$qvals = $state[ $root_slug ];
	?>
		<div class="bimeson-filter-key" data-key="<?php echo $_slug ?>">
			<div class="bimeson-filter-key-inner">
				<input type="checkbox" class="bimeson-filter-switch tgl tgl-light" id="<?php echo $_slug ?>" name="<?php echo $_slug ?>" <?php if ( ! empty( $qvals ) ) echo 'checked' ?> value="1"></input>
				<label class="tgl-btn" for="<?php echo $_slug ?>"></label>
				<div class="bimeson-filter-cbs">
					<div class="bimeson-filter-cat"><?php echo $_cat_name ?></div>
	<?php
		foreach ( $terms as $t ) :
			$_id  = esc_attr( $this->sub_term_to_id( $root_slug, $t ) );
			$_val = esc_attr( $t->slug );
			if ( $filtered !== false && ! in_array( $t->slug, $filtered, true ) ) continue;
			if ( class_exists( '\st\Multilang' ) ) {
				$_name = esc_html( \st\Multilang::get_instance()->get_term_name( $t ) );
			} else {
				$_name = esc_html( $t->name );
			}
	?>
					<label>
						<input type="checkbox" id="<?php echo $_id ?>" name="<?php echo $_id ?>" <?php if ( in_array( $t->slug, $qvals, true ) ) echo 'checked' ?> value="<?php echo $_val ?>"></input>
						<?php echo $_name ?>
					</label>
	<?php
		endforeach;
	?>
				</div>
			</div>
		</div>
	<?php
	}

	public function get_filter_state_from_meta( $post ) {
		$slug_to_terms = $this->get_root_slugs_to_sub_terms();
		$ret = json_decode( get_post_meta( $post->ID, Bimeson_Admin::FLD_JSON_PARAMS, true ), true );

		foreach ( $slug_to_terms as $slug => $terms ) {
			if ( ! isset( $ret[ $slug ] ) ) $ret[ $slug ] = [];
		}
		return $ret;
	}

	public function get_filter_state_from_qvar() {
		$slug_to_terms = $this->get_root_slugs_to_sub_terms();
		$ret = [];

		foreach ( $slug_to_terms as $slug => $terms ) {
			$val = get_query_var( $this->_get_query_var_name( $slug ) );
			$temp = empty( $val ) ? [] : explode( ',', $val );
			$ret[ $slug ] = $temp;
		}
		$val = get_query_var( self::QVAR_YEAR );
		$ret[ self::KEY_YEAR ] = $val;
		return $ret;
	}

	public function get_filter_state_from_post() {
		$slug_to_terms = $this->get_root_slugs_to_sub_terms();
		$ret = [];

		foreach ( $slug_to_terms as $slug => $terms ) {
			if ( ! isset( $_POST[ $slug ] ) ) continue;
			$temp = [];

			foreach ( $terms as $t ) {
				$id  = $this->sub_term_to_id( $slug, $t );
				$val = isset( $_POST[ $id ] ) ? $_POST[ $id ] : false;
				if ( $val ) $temp[] = $t->slug;
			}
			$ret[ $slug ] = $temp;
		}
		return $ret;
	}


	// -------------------------------------------------------------------------

	public function get_slug_to_last_omit() {
		$rs_to_sub_terms = $this->get_root_slugs_to_sub_terms();
		$slug_to_last_omit = [];

		foreach ( $rs_to_sub_terms as $rs => $terms ) {
			foreach ( $terms as $t ) {
				$val = get_term_meta( $t->term_id, self::KEY_LAST_CAT_OMITTED, true );
				if ( $val === '1' ) $slug_to_last_omit[ $t->slug ] = true;
			}
		}
		return $slug_to_last_omit;
	}

	public function get_sub_slug_to_ancestors() {
		$rs_to_sub_terms = $this->get_root_slugs_to_sub_terms();
		$keys = [];

		foreach ( $rs_to_sub_terms as $rs => $terms ) {
			foreach ( $terms as $t ) {
				$a = $this->_get_sub_slug_to_ancestors( $this->term_to_taxonomy( $rs ), $t );
				if ( ! empty( $a ) ) $keys[ $t->slug ] = $a;
			}
		}
		return $keys;
	}

	private function _get_sub_slug_to_ancestors( $sub_tax, $term ) {
		$ret = [];
		while ( true ) {
			$pid = $term->parent;
			if ( $pid === 0 ) break;
			$term = get_term_by( 'id', $pid, $sub_tax );
			$ret[] = $term->slug;
		}
		return array_reverse( $ret );
	}

	public function get_root_slugs_to_sub_depths() {
		$rs_to_sub_terms = $this->get_root_slugs_to_sub_terms();
		$rs_to_depth = [];

		foreach ( $rs_to_sub_terms as $rs => $terms ) {
			$depth = 0;
			foreach ( $terms as $t ) {
				$d = $this->_get_sub_tax_depth( $this->term_to_taxonomy( $rs ), $t );
				if ( $depth < $d ) $depth = $d;
			}
			$rs_to_depth[ $rs ] = $depth;
		}
		return $rs_to_depth;
	}

	private function _get_sub_tax_depth( $sub_tax, $term ) {
		$ret = 1;
		while ( true ) {
			$pid = $term->parent;
			if ( $pid === 0 ) break;
			$term = get_term_by( 'id', $pid, $sub_tax );
			$ret++;
		}
		return $ret;
	}


	// Callback Functions ------------------------------------------------------

	public function _cb_taxonomy_edit_form_fields( $term, $taxonomy ) {
		if ( $taxonomy === $this->_tax_root ) {
			self::_boolean_form( $term, self::KEY_IS_HIDDEN, $this->_labels['hide_from_view'] );
		} else {
			self::_boolean_form( $term, self::KEY_LAST_CAT_OMITTED, $this->_labels['omit_last_cat'] );
		}
	}

	public function _cb_edit_taxonomy( $term_id, $taxonomy ) {
		if ( $taxonomy !== $this->_tax_root ) return;

		$term = get_term_by( 'id', $term_id, $taxonomy );
		$s = $term->slug;
		if ( 32 < strlen( $s ) + strlen( $this->_tax_sub_base ) ) {
			$s = substr( $s, 0, 32 - ( strlen( $this->_tax_sub_base ) ) );
			wp_update_term( $term_id, $taxonomy, [ 'slug' => $s ] );
		}

		$this->_old_taxonomy = $this->term_to_taxonomy( $term );

		$terms = get_terms( $this->_old_taxonomy, [ 'hide_empty' => 0 ] );
		foreach ( $terms as $t ) {
			$this->_old_terms[] = [ 'slug' =>  $t->slug, 'name' => $t->name, 'term_id' => $t->term_id ];
		}
	}

	public function _cb_edited_taxonomy( $term_id, $taxonomy ) {
		self::_is_not_empty( $term_id, self::KEY_LAST_CAT_OMITTED );
		self::_is_not_empty( $term_id, self::KEY_IS_HIDDEN );

		if ( $taxonomy !== $this->_tax_root ) return;

		$term = get_term_by( 'id', $term_id, $this->_tax_root );
		$new_taxonomy = $this->term_to_taxonomy( $term );

		if ( $this->_old_taxonomy !== $new_taxonomy ) {
			$this->register_sub_tax( $new_taxonomy, $term->name );
			foreach ( $this->_old_terms as $t ) {
				wp_delete_term( $t['term_id'], $this->_old_taxonomy );
				wp_insert_term( $t['name'], $new_taxonomy, [ 'slug' => $t['slug'] ] );
			}
		}
	}

	public function _cb_query_vars( $query_vars ) {
		$roots = $this->_get_root_terms();
		foreach ( $roots as $r ) {
			$query_vars[] = $this->_get_query_var_name( $r->slug );
		}
		$query_vars[] = self::QVAR_YEAR;
		return $query_vars;
	}

	static private function _boolean_form( $term, $key, $label ) {
		$val = get_term_meta( $term->term_id, $key, true );
		?>
		<tr class="form-field">
			<th style="padding-top: 20px; padding-bottom: 20px;"><label for="<?php echo $key ?>"><?php echo esc_html( $label ) ?></label></th>
			<td style="padding-top: 20px; padding-bottom: 20px;">
				<input type="checkbox" name="<?php echo $key ?>" id="<?php echo $key ?>" <?php checked( $val, 1 ) ?>/>
			</td>
		</tr>
		<?php
	}

	static private function _is_not_empty( $term_id, $key ) {
		if ( empty( $_POST[ $key ] ) ) {
			delete_term_meta( $term_id, $key );
		} else {
			update_term_meta( $term_id, $key, 1 );
		}
	}

}
