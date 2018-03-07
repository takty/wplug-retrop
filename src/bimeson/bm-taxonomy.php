<?php
namespace st;

/**
 *
 * Bimeson (Taxonomy)
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-03-07
 *
 */


class Bimeson_Taxonomy {

	const DEFAULT_TAXONOMY     = 'bm_cat';
	const DEFAULT_SUB_TAX_BASE = 'bm_cat_';

	private $_label;
	private $_tax_root;
	private $_tax_sub_base;
	private $_post_type;

	private $_old_taxonomy = [];
	private $_old_terms = [];

	public function __construct( $post_type, $labels, $taxonomy = false, $sub_tax_base = false ) {
		$this->_label        = $labels['taxonomy'];
		$this->_tax_root     = ( $taxonomy === false ) ? self::DEFAULT_TAXONOMY : $taxonomy;
		$this->_tax_sub_base = ( $sub_tax_base === false ) ? self::DEFAULT_SUB_TAX_BASE : $sub_tax_base;
		$this->_post_type    = $post_type;

		register_taxonomy( $this->_tax_root, $this->_post_type, [
			'hierarchical'       => false,
			'label'              => $this->_label,
			'public'             => false,
			'show_ui'            => true,
			'show_in_quick_edit' => false,
			'meta_box_cb'        => false,
			'rewrite'            => false,
		] );
		register_taxonomy_for_object_type( $this->_tax_root, $this->_post_type );
		\st\ordered_term\make_terms_ordered( [ $this->_tax_root ] );

		$terms = get_terms( $this->_tax_root, [ 'hide_empty' => 0 ]  );
		$sub_taxes = [];
		foreach ( $terms as $t ) {
			$sub_tax = $this->term_to_taxonomy( $t );
			$sub_taxes[] = $sub_tax;
			$this->register_sub_tax( $sub_tax, $t->name );
		}
		\st\ordered_term\make_terms_ordered( $sub_taxes );

		add_action( "edit_terms",                          [ $this, '_cb_edit_taxonomy' ], 10, 2 );
		add_action( "edited_{$this->_tax_root}",           [ $this, '_cb_edited_taxonomy' ], 10, 2 );
		add_filter( 'query_vars',                          [ $this, '_cb_query_vars' ] );
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

	public function get_taxonomy() {
		return $this->_tax_root;
	}

	public function get_root_slugs() {
		$terms = get_terms( $this->_tax_root, [ 'hide_empty' => 0 ]  );
		$slugs = [];
		foreach( $terms as $t ) {
			$slugs[] = $t->slug;
		}
		return $slugs;
	}

	public function get_sub_taxonomies() {
		$terms = $this->get_root_slugs();
		$slugs = [];
		foreach( $terms as $t ) {
			$slugs[] = $this->term_to_taxonomy( $t );
		}
		return $slugs;
	}

	public function get_root_slugs_to_sub_slugs() {
		$roots = get_terms( $this->_tax_root, [ 'hide_empty' => 0 ]  );
		$slugs = [];
		foreach( $roots as $r ) {
			$sub_tax = $this->term_to_taxonomy( $r );
			$terms = get_terms( $sub_tax, [ 'hide_empty' => 0 ]  );
			$slugs[ $r->slug ] = array_map( function ( $t ) { return $t->slug; }, $terms );;
		}
		return $slugs;
	}

	public function show_tax_checkboxes( $terms, $slug ) {
		$v = get_query_var( $this->get_query_var_name( $slug ) );
		$_slug = esc_attr( $slug );
		$qvals = empty( $v ) ? [] : explode( ',', $v );
	?>
		<div class="bm-list-filter-cat" data-key="<?php echo $_slug ?>">
			<div class="bm-list-filter-cat-inner">
				<input type="checkbox" class="bm-list-filter-switch tgl tgl-light" id="<?php echo $_slug ?>" <?php if ( ! empty( $qvals ) ) echo 'checked' ?>></input>
				<label class="tgl-btn" for="<?php echo $_slug ?>"></label>
				<div class="bm-list-filter-cbs">
	<?php
		foreach ( $terms as $t ) :
			$_id   = esc_attr( str_replace( '_', '-', "{$this->_tax_sub_base}{$t->slug}" ) );
			$_val  = esc_attr( $t->slug );
			if ( class_exists( '\st\Multilang' ) ) {
				$ml = \st\Multilang::get_instance();
				$_name = esc_html( $ml->get_term_name( $t ) );
			} else {
				$_name = esc_html( $t->name );
			}
	?>
					<label>
						<input type="checkbox" id="<?php echo $_id ?>" <?php if ( in_array( $t->slug, $qvals, true ) ) echo 'checked' ?> data-val="<?php echo $_val ?>"></input>
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


	// Callback Functions ------------------------------------------------------

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

	public function _cb_edit_taxonomy( $term_id, $taxonomy ) {
		if ( $taxonomy !== $this->_tax_root ) return;

		$term = get_term_by( 'id', $term_id, $taxonomy );
		$s = $term->slug;
		if ( 32 < strlen( $s ) + strlen( $this->_tax_sub_base ) ) {
			$s = substr( $s, 0, 32 - ( strlen( $this->_tax_sub_base ) ) );
			wp_update_term( $term_id, $taxonomy, [ 'slug' => $s ] );
		}

		$this->_old_taxonomy = $this->term_to_taxonomy( $term );

		$terms = get_terms( $this->_old_taxonomy, [ 'hide_empty' => 0 ]  );
		foreach ( $terms as $t ) {
			$this->_old_terms[] = [ 'slug' =>  $t->slug, 'name' => $t->name, 'term_id' => $t->term_id ];
		}
	}

	public function _cb_edited_taxonomy( $term_id, $tt_id ) {
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
		$top_keys = get_terms( $this->_tax_root, [ 'hide_empty' => 0, 'parent' => '0' ] );
		foreach ( $top_keys as $t ) {
			$query_vars[] = $this->get_query_var_name( $t->slug );
		}
		return $query_vars;
	}

	public function get_query_var_name( $slug ) {
		$slug = str_replace( '-', '_', $slug );
		return "{$this->_tax_sub_base}{$slug}";
	}

	public function register_sub_tax( $tax, $name ) {
		register_taxonomy( $tax, $this->_post_type, [
			'hierarchical'      => true,
			'label'             => "{$this->_label} ($name)",
			'public'            => true,
			'show_ui'           => true,
			'rewrite'           => false,
			'sort'              => true,
			'show_admin_column' => true
		] );
	}

}
