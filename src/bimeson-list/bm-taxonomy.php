<?php
namespace st;

/**
 *
 * Bimeson (Taxonomy)
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-02-23
 *
 */


class Bimeson_Taxonomy {

	const DEFAULT_TAXONOMY = 'pub_key';

	const KEY_LAST_KEY_OMITTED = '_bimeson_pub_last_key_omitted';
	const KEY_LAST_KEY_HIDDEN  = '_bimeson_pub_last_key_hidden';

	private $_taxonomy;

	public function __construct( $taxonomy = false ) {
		$this->_taxonomy = ( $taxonomy === false ) ? self::DEFAULT_TAXONOMY : $taxonomy;

		register_taxonomy( $this->_taxonomy, 'page', [
			'hierarchical'       => true,
			'label'              => '研究業績キー',
			'public'             => false,
			'show_ui'            => true,
			'show_in_quick_edit' => false,
			'meta_box_cb'        => false,
			'rewrite'            => false,
		] );
		\st\ordered_term\make_terms_ordered( [ $this->_taxonomy ] );

		add_action( "{$this->_taxonomy}_edit_form_fields", [ $this, '_cb_taxonomy_edit_form_fields' ], 10, 2 );
		add_action( "edited_{$this->_taxonomy}", [ $this, '_cb_edited_taxonomy' ], 10, 2 );
		add_filter( 'query_vars', [ $this, '_cb_query_vars' ] );
	}

	public function get_taxonomy() {
		return $this->_taxonomy;
	}

	public function get_slug_to_child_term_ids( $omit = false, $do_hidden_option = false ) {
		$roots = get_terms( $this->_taxonomy, [ 'hide_empty' => 0, 'parent' => '0' ] );
		$slug_to_term_ids = [];
		foreach ( $roots as $idx => $t ) {
			if ( $omit && $idx === 0 ) continue;
			if ( $do_hidden_option ) {
				$val_hide = get_term_meta( $t->term_id, self::KEY_LAST_KEY_HIDDEN, true );
				if ( $val_hide ) continue;
			}
			$terms = get_terms( $this->_taxonomy, [ 'hide_empty' => 0, 'parent' => $t->term_id ] );
			$slug_to_term_ids[ $t->slug ] = array_map( function ( $x ) { return $x->term_id; }, $terms );
		}
		return $slug_to_term_ids;
	}

	public function get_pub_key_ancestor() {
		$ts = get_terms( $this->_taxonomy, [ 'hide_empty' => 0 ] );
		$keys = [];
		foreach ( $ts as $t ) {
			$a = $this->_get_pub_key_antecedent( $t );
			if ( ! empty( $a ) ) $keys[ $t->slug ] = $a;
		}
		return $keys;
	}

	public function get_pub_key_order() {
		$roots = get_terms( $this->_taxonomy, [ 'hide_empty' => 0, 'parent' => '0' ] );
		$keys = [];

		foreach ( $roots as $r ) {
			$terms = get_terms( $this->_taxonomy, [ 'hide_empty' => 0, 'parent' => $r->term_id ] );
			$ret = [];
			foreach ( $terms as $t ) $ret[] = $t;
			$this->_get_pub_key_child( $terms, $ret );

			$orders = [];
			foreach ( $ret as $index => $tc ) $orders[ $tc->slug ] = $index;
			$keys[ $r->slug ] = $orders;
		}
		$keys['__root__']  = array_map( function ( $x ) { return $x->slug; }, $roots );
		$keys['__depth__'] = $this->get_pub_key_depths();
		return $keys;
	}

	public function show_key_checkboxes( $term_ids, $term_parent_slug, $selected = '' ) {
		$v = get_query_var( "pub-$term_parent_slug" );
		$_slug = esc_attr( $term_parent_slug );
		$qvals = empty( $v ) ? [] : explode( ',', $v );
	?>
		<div class="pub-list-filter-key" data-key="<?php echo $_slug ?>">
			<div class="pub-list-filter-key-inner">
				<input type="checkbox" class="pub-list-filter-switch tgl tgl-light" id="<?php echo $_slug ?>" <?php if ( ! empty( $qvals ) ) echo 'checked' ?>></input>
				<label class="tgl-btn" for="<?php echo $_slug ?>"></label>
				<div class="pub-list-filter-cbs">
	<?php
		foreach ( $term_ids as $t_id ) :
			$t = get_term( $t_id, $this->_taxonomy );
			$_id  = esc_attr( "{$this->_taxonomy}-{$t->slug}" );
			$_val = esc_attr( $t->slug );
	?>
					<label>
						<input type="checkbox" id="<?php echo $_id ?>" <?php if ( in_array( $t->slug, $qvals, true ) ) echo 'checked' ?> data-val="<?php echo $_val ?>"></input>
						<?php echo esc_html( $t->name ) ?>
					</label>
	<?php
		endforeach;
	?>
				</div>
			</div>
		</div>
	<?php
	}

	public function get_pub_key_depths() {
		$roots = get_terms( $this->_taxonomy, [ 'hide_empty' => 0, 'parent' => '0' ] );
		$top_key_to_depth = [];
		$keys = [];

		foreach ( $roots as $t ) {
			$terms = get_terms( $this->_taxonomy, [ 'hide_empty' => 0, 'child_of' => $t->term_id ] );
			$depth = 0;
			foreach ( $terms as $tc ) {
				$d = $this->_get_pub_key_depth( $tc );
				if ( $depth < $d ) $depth = $d;
			}
			$top_key_to_depth[ $t->slug ] = $depth;
		}
		return $top_key_to_depth;
	}

	public function get_pub_key_roots() {
		$roots = get_terms( $this->_taxonomy, [ 'hide_empty' => 0, 'parent' => '0' ] );
		return array_map( function ( $x ) { return $x->slug; }, $roots );
	}

	public function get_pub_key_last_omit() {
		$roots = get_terms( $this->_taxonomy, [ 'hide_empty' => 0, 'parent' => '0' ] );
		$val_to_last_omit = [];

		foreach ( $roots as $r ) {
			$terms = get_terms( $this->_taxonomy, [ 'hide_empty' => 0, 'child_of' => $r->term_id ] );

			foreach ( $terms as $t ) {
				$val = get_term_meta( $t->term_id, self::KEY_LAST_KEY_OMITTED, true );
				$val_to_last_omit[ $t->slug ] = ($val === '1');
			}
		}
		return $val_to_last_omit;
	}


	// Callback Functions ------------------------------------------------------

	public function _cb_taxonomy_edit_form_fields( $term, $taxonomy ) {
		self::_boolean_form( $term, self::KEY_LAST_KEY_OMITTED, '一番最後のキーを省略' );
		if ( $term->parent === '0' ) {
			self::_boolean_form( $term, self::KEY_LAST_KEY_HIDDEN, '閲覧画面から隠す' );
		}
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

	public function _cb_edited_taxonomy( $term_id, $taxonomy ) {
		self::_is_not_empty( $term_id, self::KEY_LAST_KEY_OMITTED );
		self::_is_not_empty( $term_id, self::KEY_LAST_KEY_HIDDEN );
	}

	static private function _is_not_empty( $term_id, $key ) {
		if ( empty( $_POST[ $key ] ) ) {
			delete_term_meta( $term_id, $key );
		} else {
			update_term_meta( $term_id, $key, 1 );
		}
	}

	public function _cb_query_vars( $query_vars ) {
		$top_keys = get_terms( $this->_taxonomy, [ 'hide_empty' => 0, 'parent' => '0' ] );
		foreach ( $top_keys as $t ) {
			$query_vars[] = "pub-{$t->slug}";
		}
		return $query_vars;
	}


	// Private Functions -------------------------------------------------------

	private function _get_pub_key_antecedent( $term ) {
		$ret = [];
		while ( true ) {
			$pid = $term->parent;
			if ( $pid === 0 ) break;
			$term = get_term_by( 'id', $pid, $this->_taxonomy );
			if ( $term->parent === 0 ) break;
			$ret[] = $term->slug;
		}
		return $ret;
	}

	private function _get_pub_key_child( $terms, &$ret ) {
		$css = [];
		foreach ( $terms as $t ) {
			$cs = get_terms( $this->_taxonomy, [ 'hide_empty' => 0, 'parent' => $t->term_id ] );
			foreach ( $cs as $c ) {
				$css[] = $c;
				$ret[] = $c;
			}
		}
		if ( ! empty( $css ) ) $this->_get_pub_key_child( $css, $ret );
	}

	private function _get_pub_key_depth( $term ) {
		$ret = 0;
		while ( true ) {
			$pid = $term->parent;
			if ( $pid === 0 ) break;
			$term = get_term_by( 'id', $pid, $this->_taxonomy );
			$ret++;
		}
		return $ret;
	}

}
