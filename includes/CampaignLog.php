<?php

class CampaignLog {
	private static $basic_fields = [
		'start', 'end', 'enabled', 'preferred', 'locked', 'geo', 'buckets'
	];
	private static $list_fields = [ 'projects', 'languages', 'countries' ];
	private static $map_fields = [ 'banners' ];

	/** @var string[]|int[] */
	private $begin;
	/** @var string[]|int[] */
	private $end;
	/** @var string */
	private $campaign;
	/** @var string */
	private $action;
	/** @var string|int */
	private $timestamp;
	/** @var string|null */
	private $comment;

	/**
	 * @param stdClass|null $row
	 */
	function __construct( $row = null ) {
		$begin = [];
		$end = [];
		if ( $row ) {
			$comma_explode = function ( $str ) {
				return explode( ", ", $str );
			};

			$json_decode = function ( $json ) {
				return json_decode( $json, true );
			};

			$store = function ( $name, $decode = null ) use ( $row, &$begin, &$end ) {
				$beginField = 'notlog_begin_' . $name;
				$endField = 'notlog_end_' . $name;

				if ( !$decode ) {
					$decode = function ( $v ) {
						return $v;
					};
				}
				$begin[ $name ] = $decode( $row->$beginField );
				$end[ $name ] = $decode( $row->$endField );
			};

			foreach ( static::$basic_fields as $name ) {
				$store( $name );
			}
			foreach ( static::$list_fields as $name ) {
				$store( $name, $comma_explode );
			}
			foreach ( static::$map_fields as $name ) {
				$store( $name, $json_decode );
			}
		}
		$this->begin = $begin;
		$this->end = $end;

		$this->campaign = $row->notlog_not_name;
		$this->action = $row->notlog_action;
		$this->timestamp = $row->notlog_timestamp;

		// TODO temporary code for soft dependency on schema change
		if ( property_exists( $row, 'notlog_comment' ) ) {
			$this->comment = ( $row->notlog_comment !== null )
				? $row->notlog_comment : '';
		}
	}

	# TODO: use in logpager
	function changes() {
		$removed = [];
		$added = [];

		# XXX cannot use "this" in closures until php 5.4
		$begin =& $this->begin;
		$end =& $this->end;

		$diff_basic = function ( $name ) use ( &$removed, &$added, &$begin, &$end ) {
			if ( $begin[ $name ] !== $end[ $name ] ) {
				if ( $begin[ $name ] !== null ) {
					$removed[ $name ] = $begin[ $name ];
				}
				if ( $end[ $name ] !== null ) {
					$added[ $name ] = $end[ $name ];
				}
			}
		};
		$diff_list = function ( $name ) use ( &$removed, &$added, &$begin, &$end ) {
			if ( $begin[ $name ] !== $end[ $name ] ) {
				$removed[ $name ] = array_diff( $begin[ $name ], $end[ $name ] );
				if ( !$removed[ $name ] || $removed[ $name ] === [ "" ] ) {
					unset( $removed[ $name ] );
				}
				$added[ $name ] = array_diff( $end[ $name ], $begin[ $name ] );
				if ( !$added[ $name ] || $added[ $name ] === [ "" ] ) {
					unset( $added[ $name ] );
				}
			}
		};
		$diff_map = function ( $name ) use ( &$removed, &$added, &$begin, &$end ) {
			$removed[ $name ] = $begin[ $name ];
			$added[ $name ] = $end[ $name ];

			if ( $begin[ $name ] && $end[ $name ] ) {
				$all_keys = array_keys( array_merge( $begin[ $name ], $end[ $name ] ) );
				foreach ( $all_keys as $item ) {
					# simplification: match contents, but diff at item level
					if ( array_key_exists( $item, $begin[ $name ] )
						&& array_key_exists( $item, $end[ $name ] )
						&& $added[ $name ][ $item ] === $removed[ $name ][ $item ]
					) {
						unset( $added[ $name ][ $item ] );
						unset( $removed[ $name ][ $item ] );
					}
				}
			}
			if ( !$removed[ $name ] ) {
				unset( $removed[ $name ] );
			}
			if ( !$added[ $name ] ) {
				unset( $added[ $name ] );
			}
		};
		foreach ( static::$basic_fields as $name ) {
			$diff_basic( $name );
		}
		foreach ( static::$list_fields as $name ) {
			$diff_list( $name );
		}
		foreach ( static::$map_fields as $name ) {
			$diff_map( $name );
		}

		return [ 'removed' => $removed, 'added' => $added ];
	}
}
