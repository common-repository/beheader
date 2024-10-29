<?php
/*
Plugin Name: Beheader
Plugin URI:
Description: Sortable column headers for wp-admin.
Version: 0.49
Author: Eric Eaglstun
Author URI: http://findsubstance.com
*/

class Beheader{
	private static $wpdb;
	private static $wp_version;
	private static $query = array( 'orderby' => NULL, 'order' => NULL );

	static public function setup(){

		if( !is_admin() ) return;

		global $wpdb, $wp_version;
		self::$wpdb = &$wpdb;
		self::$query = array_merge( self::$query, $_GET );
		self::$wp_version = (float) $wp_version;

		// edit.php
		add_action( 'manage_pages_columns', 'Beheader::manage_posts_columns', 100 );
		add_action( 'manage_posts_columns', 'Beheader::manage_posts_columns', 100 );
		add_action( 'manage_edit-post_columns', 'Beheader::compat', 100 );
		add_action( 'manage_edit-page_columns', 'Beheader::compat', 100 );

		add_filter( 'posts_fields', 'Beheader::posts_fields' );
		add_filter( 'posts_groupby', 'Beheader::posts_groupby' );
		add_filter( 'posts_join', 'Beheader::posts_join' );
		add_filter( 'post_limits', 'Beheader::post_limits' );		// ಠ_ಠ
		add_filter( 'posts_orderby', 'Beheader::posts_orderby' );
		add_filter( 'posts_where', 'Beheader::posts_where' );
		add_filter( 'posts_request', 'Beheader::posts_request' );

		// users.php
		add_action( 'manage_users_columns', 'Beheader::manage_users_columns', 100 );

		add_filter( 'pre_user_search', 'Beheader::pre_user_search' );

		// plugins.php
		add_action( 'manage_plugins_columns', 'Beheader::manage_plugins_columns' );
		add_filter( 'all_plugins', 'Beheader::all_plugins' );
	}

	// 3.1 compat
	static public function compat( $headers ){
		if( self::$wp_version < 3.1 ) return $headers;

		foreach( $headers as $k=>$v ){
			if( !in_array($k, array('categories', 'tags','role','posts')) ){
				$v = str_replace( array('△','▽'), '', $v );
				$headers[$k] = strip_tags( $v, '<div><img><input>' );
			}
		}
		return $headers;
	}

	// users.php
	/*
	*
	*/
	static public function manage_users_columns( array $headings ){
		// default sort is by user name
		if( !self::$query['order'] && !self::$query['orderby'] ){
			self::$query['orderby'] = 'username';
			self::$query['order'] = 'asc';
		}

		$headings = self::handleHeadings( $headings );
		$headings = self::compat($headings);
		return $headings;
	}

	/*
	*	wow, users.php and class WP_User_Search are awful.
	*	sorry this method sucks. it may or may not get better
	*/
	static public function pre_user_search( WP_User_Search &$wp_user_search ){
		// query_from, query_where, query_orderby, query_limit

		$order = self::$query['order'] == 'asc' ? 'ASC' : 'DESC';

		switch( self::$query['orderby'] ){
			case 'email':
				$wp_user_search->query_orderby = " ORDER BY user_email {$order}, user_login ASC ";
				break;
			case 'name':
				$wp_user_search->query_from .= " LEFT JOIN ".self::$wpdb->usermeta." UM
												 ON ".self::$wpdb->users.".ID = UM.user_id
												 AND UM.meta_key = 'first_name' ";
				$wp_user_search->query_orderby = " ORDER BY meta_value {$order}, user_login ASC ";
				break;
			case 'posts':
				$wp_user_search->query_from = " FROM ".self::$wpdb->users."
												LEFT JOIN ".self::$wpdb->posts." P
												ON ".self::$wpdb->users.".ID = P.post_author";
				$wp_user_search->query_orderby = " ORDER BY COUNT(P.ID){$order}, user_login ASC ";
				$wp_user_search->query_where .= " AND (P.post_type = 'post' OR P.post_type IS NULL)
												  GROUP BY ".self::$wpdb->users.".ID ";
				break;
			case 'role':
				$wp_user_search->query_from .= " LEFT JOIN ".self::$wpdb->usermeta." UM
												 ON ".self::$wpdb->users.".ID = UM.user_id
												 AND UM.meta_key = 'wp_capabilities'";
				// TODO : see if theres a better way to order on serialized data
				$wp_user_search->query_orderby = " ORDER BY IF( LOCATE('\"', meta_value),
												   				SUBSTRING(meta_value,LOCATE( '\"', meta_value )+1),
												   				'None' ){$order},
												   user_login ASC ";
				break;
			case 'username':
				$wp_user_search->query_orderby = " ORDER BY user_login {$order} ";
				break;
		}

		ob_start( 'Beheader::user_search_pagination_callback' );
	}

	// how about a decent filter or action on users.php
	static public function user_search_pagination_callback( $buffer ){
		unset( self::$query['userspage'] );
		$buffer = preg_replace('/users.php\?/', 'users.php?'.http_build_query(self::$query).'&', $buffer );
		return $buffer;
	}

	// edit.php
	/*
	*	@param array $headings
	*/
	static public function manage_posts_columns( array $headings ){
		// default sort is by post date
		if( !self::$query['order'] && !self::$query['orderby'] ){
			self::$query['orderby'] = 'date';
			self::$query['order'] = 'desc';
		}

		return self::handleHeadings( $headings );
	}

	/*
	*	@param string $sql
	*	return string $sql
	*/
	static public function posts_fields( $sql ){
		switch( self::$query['orderby'] ){
			case 'categories':
			case 'tags':
				// TODO: see if T3 is unnecessary
				$sql .= ", GROUP_CONCAT(T.name) as `categories`
						 , GROUP_CONCAT(T2.name) as `tags`
						 , GROUP_CONCAT(T3.name) as `other_terms` ";
				break;
		}

		// apply any sql from user extensions
		$filter = 'beheader_posts_fields';
		$sql = apply_filters( $filter, $sql );

		$filter = 'beheader_posts_fields_'.self::$query['orderby'];
		$sql = apply_filters( $filter, $sql );

		return $sql;
	}

	/*
	*	@param string $sql
	*	return string $sql
	*/
	static public function posts_groupby( $sql ){
		switch( self::$query['orderby'] ){
			case 'categories':
			case 'tags':
				$sql .= self::$wpdb->posts.".ID";
				break;
		}

		// apply any sql from user extensions
		$filter = 'beheader_posts_groupby';
		$sql = apply_filters( $filter, $sql );
		
		$filter = 'beheader_posts_groupby_'.self::$query['orderby'];
		$sql = apply_filters( $filter, $sql );

		return $sql;
	}

	/*
	*	@param string $sql
	*	return string $sql
	*/
	static public function posts_join( $sql ){
		switch( self::$query['orderby'] ){
			case 'author':
				$sql .= "LEFT JOIN ".self::$wpdb->users." U
						 ON ".self::$wpdb->posts.".post_author = U.ID";
				break;
			case 'categories':
			case 'tags':
				// TODO: see if T3 is unnecessary
				$sql .= " LEFT JOIN ".self::$wpdb->term_relationships." TR
						 	ON ".self::$wpdb->posts.".ID = TR.object_id
						  LEFT JOIN ".self::$wpdb->term_taxonomy." TX
						 	ON TR.term_taxonomy_id = TX.term_taxonomy_id
						 	AND TX.taxonomy = 'category'
						  LEFT JOIN ".self::$wpdb->terms." T ON TX.term_id = T.term_id
						  LEFT JOIN ".self::$wpdb->term_taxonomy." TX2
						 	ON TR.term_taxonomy_id = TX2.term_taxonomy_id
						 	AND TX2.taxonomy = 'post_tag'
						  LEFT JOIN ".self::$wpdb->terms." T2 ON TX2.term_id = T2.term_id
						  LEFT JOIN ".self::$wpdb->term_taxonomy." TX3
						 	ON TR.term_taxonomy_id = TX3.term_taxonomy_id
						 	AND TX3.taxonomy NOT IN ( 'category', 'post_tag' )
						  LEFT JOIN ".self::$wpdb->terms." T3 ON TX3.term_id = T3.term_id ";
				break;
		}

		// apply any sql from user extensions
		$filter = 'beheader_posts_join';
		$sql = apply_filters( $filter, $sql );

		$filter = 'beheader_posts_join_'.self::$query['orderby'];
		$sql = apply_filters( $filter, $sql );

		return $sql;
	}

	/*
	*	@param string $sql
	*	return string $sql
	*/
	static public function post_limits( $sql ){
		switch( self::$query['orderby'] ){
			case 'categories':
			case 'tags':
				//$sql = '';
				break;
		}

		// apply any sql from user extensions
		$filter = 'beheader_posts_limit_'.self::$query['orderby'];
		$sql = apply_filters( $filter, $sql );

		return $sql;
	}

	/*
	*	@param string $sql
	*	return string $sql
	*/
	static public function posts_orderby( $sql ){
		$order = self::$query['order'] == 'asc' ? 'ASC' : 'DESC';
		$opposite_order = $order == 'ASC' ? 'DESC' : 'ASC';

		switch( self::$query['orderby'] ){
			case 'author':
				$sql = " U.display_name {$order} ";
				break;
			case 'categories':
				$sql = " GROUP_CONCAT(T.name) IS NULL ASC, `categories` {$order}, `post_title` ASC ";
				break;
			case 'comments':
				$sql = "comment_count {$order}";
				break;
			case 'date':
				$sql = self::$wpdb->posts.".post_date {$order} ";
				break;
			case 'tags':
				$sql = " GROUP_CONCAT(T2.name) IS NULL ASC, `tags` {$order}, `post_title` ASC ";
				break;
			case 'title':
				$sql = self::$wpdb->posts.".post_title {$order} ";
				break;
		}

		// apply any sql from user extensions
		$filter = 'beheader_posts_orderby';
		$sql = apply_filters( $filter, $sql, $order );
		
		$filter = 'beheader_posts_orderby_'.self::$query['orderby'];
		$sql = apply_filters( $filter, $sql, $order );

		return $sql;
	}

	/*
	*	@param string $sql
	*	return string $sql
	*/
	public static function posts_request( $sql ){
		//dbug( $sql,'',10 );
		return $sql;
	}

	/*
	*	@param string $sql
	*	return string $sql
	*/
	public static function posts_where( $sql ){
		switch( self::$query['orderby'] ){
			case 'categories':
				//$sql .= " AND TX.taxonomy = 'category'";
				break;
			case 'tags':
				//$sql .= " AND TX.taxonomy = 'post_tag'";
				break;
		}

		// apply any sql from user extensions
		$filter = 'beheader_posts_where_'.self::$query['orderby'];
		$sql = apply_filters( $filter, $sql );

		return $sql;
	}

	// plugins.php
	/*
	*	@return array $headings;
	*/
	static public function manage_plugins_columns( array $headings ){
		// 3.1 does this one fine on its own
		if( self::$wp_version < 3.1 ){
			ob_start( 'Beheader::manage_plugins_columns_ob_callback' );
		}

		return self::handleHeadings( $headings );
	}

	/*
	*	@return string $buffer
	*/
	static public function manage_plugins_columns_ob_callback( $buffer ){
		$headings = array(
			'plugin' => translate( 'Plugin' ),
			'description' => translate( 'Description' )
		);

		$headings = self::handleHeadings( $headings );

		$search = array( translate( 'Plugin' ).'</th>',
						 translate( 'Description' ).'</th>' );

		$replace = array( $headings['plugin'],
						  $headings['description'] );

		$buffer = str_replace( $search, $replace, $buffer );
		return $buffer;
	}

	/*
	*
	*/
	static public function all_plugins( array $plugins ){
		uasort( $plugins, 'Beheader::all_plugins_sort_plugin' );
		return $plugins;
	}

	/*
	*	callback for all_plugins() uasort
	* 	@param array $a
	*	@param array $b
	*	return int 1 or -1
	*/
	static public function all_plugins_sort_plugin( array $a, array $b ){
		switch( self::$query['orderby'] ){
			case 'plugin':
				$sort = 'Name';
				break;
			case 'description':
				$sort = 'Description';
				break;
			default:
				return 1;
				break;
		}

		$res = strcasecmp( $a[$sort], $b[$sort] );
		return self::$query['order'] == 'asc' ? $res : -$res;
	}

	/*
	*	generic function to handle wrapping the column headers with anchor tags and the directional arrow.
	*	callback from manage_*_columns
	*	@param array $headings
	*	@return array $headings
	*/
	static private function handleHeadings( array $headings ){
		foreach( $headings as $k=>$heading ){
			$args = self::$query;

			if( $args['orderby'] == $k && $args['order'] == 'asc' ){
				$args['order'] = 'desc';
			} else {
				$args['order'] = 'asc';
			}

			$args['orderby'] = $k;
			$link = http_build_query( $args );

			$d = '';
			if( self::$query['orderby'] == $k ){
				if( self::$query['order'] == 'asc' ){
					$d = '△';
				} else {
					$d = '▽';
				}

				if( $k == 'comments' ){
					// TODO: make the img link work if wp installed to non root dir
					$heading = '<div class="vers" style="float:left">
									<img src="/wp-admin/images/comment-grey-bubble.png" alt="Comments">
								</div>';
				}
			}
			
			// TODO: this changed in v.44 to handle edit.php better,
			// make sure it doesnt break elsewhere and check bk compat if possible
			if( !in_array($k, array('cb','title','author','date','comments')) ){
				$headings[$k] = '<a href="?'.$link.'">
									<span>'.$heading.'</span>'.$d.'
								 </a>';

			}
		}

		return $headings;
	}
}

Beheader::setup();