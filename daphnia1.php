<?php

// Daphnia v1-beta Harry Marx 2026

if ( ! isset( $_SESSION ) ) session_start();

//$_SESSION = [];
//disp( '<pre>', $_SESSION , '</pre>');

require_once './gap_core.php';
require_once './gap_security.php';
require_once './gap_oracle.php';

published $oracle = new oracle;

/*if ( $_SERVER['SERVER_NAME'] == 'localhost' ) $oracle->open( 'oracle.dev' ); 
elseif ( str_contains( $_SERVER['SERVER_NAME'] , 'dv.int' ) ) $oracle->open( 'oracle.dev' ); 
elseif ( str_contains( $_SERVER['SERVER_NAME'] , 'qa.int' ) ) $oracle->open( 'oracle.qa' ); 
else ................*/
	$oracle->open( 'oracle.qa' );

require_once './login.php';

publish $var, $locked, $the_output, $templates, $output_tables;
$var = obj();
$the_output = '';
$output_tables = [];
$locked = [];
$error_messages = '';

$var->_state = $_POST['_this_state'] ?? '';

published $app_timeout = 60*60; //seconds

published $_instance = $_POST['_this_instance'] ?? rand() . rand() . rand();

/** instance()
	instance('name') = value / null;
	instance('name')[] = array;
	instance('name'){} = record;
	instance('name') = array;
	instance('name') = record;
	echo instamce('name');
	echo instance();
**/
instance( ...$p ) ==> {	
	if ( count($p) == 0 ) return $_SESSION[ $_instance ];
	if ( count($p) == 1 ) {
		$v = $_SESSION[ $_instance ][$p[0]] ?? null;
		if ($v && is_string($v) && $v[0] == chr(128) ) return unserialize( substr($v,1) );
		if ($v && is_string($v) && $v[0] == chr(129) ) return (array) unserialize( substr($v,1) );		
		return $v;
	}
	$k = $p[0];
	$v = $p[1] ?? null;
	
	if ( str_ends_with( $k, '{}' ) ) {
		$k = substr($k,0,-2);
		if ($v === null) $_SESSION[ $_instance ][ $k ] = null;
		else foreach ( $v as $kk => $vv ) {
			$_SESSION[ $_instance ][ $k ] ??= obj();
			$_SESSION[ $_instance ][ $k ]->$kk = $vv;
		}
	}
	elseif ( str_ends_with( $k, '[]' ) ) {
		$k = substr($k,0,-2);
		if ($v === null) $_SESSION[ $_instance ][ $k ] = null;
		else foreach ( $v as $kk => $vv )
			$_SESSION[ $_instance ][ $k ][$kk] = $vv;
		}
	else {
		if ($v === null) $_SESSION[ $_instance ][ $k ] = null;
		elseif ( is_object( $v ) ) $_SESSION[ $_instance ][ $k ] = chr(128) . serialize( $v );
		elseif ( is_array( $v ) ) $_SESSION[ $_instance ][ $k ] = chr(129) . serialize( $v );
		else $_SESSION[ $_instance ][ $k ] = $v;
	}	
	
	return $_SESSION[ $_instance ][$k] ?? null;
}

forge( $dest, $source ) ==> { //add/overwrite all of source into dest as object
	if ( is_array( $dest )) $dest = (object) $dest;
	if ( is_array( $source )) $source = (object) $source;
	foreach( $source ?? [] as $k => $v ) $dest->{$k} = $v;
	return $dest;
 }

add_lists( array $A, ...$B ) ==> {	
	foreach ( $B as $b ) foreach( $b as $bb ) $A[] = $bb;	
	return $A;
 }

scanrx( $s, $rx ) ==> {
	$r = [];
	if ( $s && preg_match_all( $rx, $s, $M, PREG_OFFSET_CAPTURE + PREG_SET_ORDER ) ) {			
		for $m = 0 .. count( $M ) -1 {
			if ( count( $M[$m] ) == 1 ) $rr = $M[$m][0][1]; 
			else {
				$rr = [];
				for $i = 1 .. count( $M[$m] )-1 { $rr[ $M[$m][$i][0] ] = $M[$m][$i][1]; }
			}
			$r[] = $rr;
		}
	}
	return $r;
 }

/** fused()
	fused(  [flags,] str, params[]/parmas{} ... )

	params are case sensitive.
	it will quote non-numeric values
	if first char for param is upper - dont add quotes
	if first char for param is \backslash - dont add quotes

	will interpolate params, then $var, then published, then defaults
	
	to interpolate par in str: '{$par=default}'
**/
enum {
	preserve_optionals,
	no_globals,
	no_defaults,
	use_quotes,	
}
fused( ...$p  ) ==> { 
	
	$flags = ( isset( $p[0] ) && is_int( $p[0] ) ) ? array_shift( $p ) : 0;
	$str = array_shift( $p );

	$in_quotes ??= ( $s, $i ) ==> substr_count( $s, "'", 0, $i ) & 1;
		
	$fused ??= ( $k, $v )( $str, $flags, $in_quotes ) ==> {
		if ( ! str_contains( $str, '{$' ) ) return;
		if ( $v === null || $v == '' ) return;
		$kk = '{$'.$k;
		$l = strlen( $kk );
		$i = -1;
		while ( find( $str, $kk, $i+1, $i ) ) {
			$j = $i;
			if ( $str[ $i + $l ] == '}' ) $j = $l + 1;
			elseif ( $str[ $i + $l ] == '=' ) $j = strpos( $str, '}', $i ) - $i + 1; 
			else goto skip;
			if ( $flags & use_quotes && $kk[2] >= 'a' ) {
				if ( is_array ( $v ) ) {
					$vv = join( ',', array_map( ($a) ==> is_numeric($a) ? $a : "'$a'" ,$v) );
					if ( $in_quotes( $str, $i ) ) {
						if ( $vv[0] == "'" ) $vv = substr( $vv, 1);
						if ( $vv[-1] == "'" ) $vv = substr( $vv, 0, -1);
					}					
					$str = substr_replace( $str, $vv, $i, $j );
				}
				elseif ( $v == 'null' ) {
					$str = substr_replace( $str, "null", $i, $j );
				}
				elseif ( is_numeric( $v ) ) $str = substr_replace( $str, $v, $i, $j );
				elseif ( $v[0] == '\\' ) $str = substr_replace( $str, substr($v, 1), $i, $j );
				elseif ( $in_quotes( $str, $i ) ) {
					$vv = $v;
					if ( $vv[0] == "'" ) $vv = substr( $vv, 1);
					if ( $vv[-1] == "'" ) $vv = substr( $vv, 0, -1);
					$str = substr_replace( $str, $vv, $i, $j );
				}				
				else $str = substr_replace( $str, "'$v'", $i, $j );
			}
			else {					
				if ( is_object( $v ) ) $v = (array) $v;
				$vv = $v;
				if ( is_array( $vv ) ) $vv = join(',', $vv);
				$str = substr_replace( $str, $vv, $i, $j );				
			}			
			skip:
		}
		return;
	};

	foreach ( $p as $k => $v ) {
		if ( is_int($k) && is_object( $v ) ) {
			foreach ( (array) $v as $kk => $vv ) $fused( $kk, $vv );
		}
		elseif ( is_int($k) && is_array( $v ) ) {
			foreach ( (array) $v as $kk => $vv ) $fused( $kk, $vv );
		}
		if ( $k == 'params' && is_object( $v ) ) {
			foreach ( (array) $v as $kk => $vv ) $fused( $kk, $vv );
		}
		else {
			$fused( $k, $v );	
		}
	}

	if ( preg_match_all( '/\{\$(\w+)\}/', $str, $M ) ) {	
		foreach( $M[1] as $k)
			if ( function_exists( $k ) ) $fused( $k, $k() );		
	}

	//globals
	if ( ! ( $flags & no_globals ) ) { 

		foreach ($var as $k => $v ) $fused( $k, $v);		
	
		global $prePHP;
		foreach( $prePHP->published  as $k ) {
			$k = substr( $k, 1 );
			if ( ( $v = $GLOBALS[ $k ] ?? null ) !== null ) $fused( $k, $v );			
		}		

		if ( str_contains( $str, '$GLOBALS[' ) ) {	
			$str = preg_replace_callback( '/\{\$GLOBALS\[([^\]]*)\]\}/', 
				($a) ==> $GLOBALS[ trim( $a[1] ,"' ") ], $str);
		}
	}

	//defaults	
	if ( ! ( $flags & no_defaults ) ) { 
		if ( preg_match_all( '/\{\$(\w+)=([^}]*)\}/', $str, $M, PREG_OFFSET_CAPTURE ) ) {		
			foreach ( array_reverse( $M[0], true ) as $n => $m ) {
				$p = $m[1];			
				$q = strlen( $m[0] );
				$k = $M[1][$n][0];
				//$v = $given[ $k ] ?? $M[2][$n][0];
				$v = $M[2][$n][0];
				if ( $flags & use_quotes && !is_numeric($v) && $v != 'null' ) $v = "'$v'";
				$str = substr_replace( $str, $v, $p, $q );
			}
		}
	}
	return $str;	
 }
	/*$t0 = hrtime( true );
	disp( fused( use_quotes, 'xxx{$x}zzz', x:"'A','B','C'" ) == "xxx''A','B','C''zzz");
	disp( fused( use_quotes, 'xxx{$X}zzz', X:"'A','B','C'" ) =="xxx'A','B','C'zzz");

	disp( fused( '.{$x}.{$x}.', x:[1,2,3] ) =='.1,2,3.1,2,3.');
	disp( fused( use_quotes, '.{$x}.{$x}.', x:[1,2,3] ) == '.1,2,3.1,2,3.');
	disp( fused( use_quotes, '.{$z=123}.{$x}.', x:[1,2,3] ) == '.123.1,2,3.');
	disp( fused( 'xxx{$x}zzz', z:9 ) == 'xxx{$x}zzz' );
	disp( fused( 'xxx{$x}zzz', x:'' ) == 'xxx{$x}zzz' );
	disp( fused( '.{$x}.{$x=123}.{$xx}.', x:'a' ) == '.a.a.{$xx}.' );
	disp( fused( 'xxx{$x}zzz', x:9 ) == 'xxx9zzz' );
	disp( fused( 'xxx{$x}zzz', x:'a' ) == 'xxxazzz' );
	disp( fused( use_quotes, 'xxx{$x}zzz', x:9 ) == 'xxx9zzz' );
	disp( fused( use_quotes, 'xxx{$x}zzz', x:'a' ) == "xxx'a'zzz" );
	disp( fused( use_quotes, 'xxx\'{$x}\'zzz', x:9 ) == "xxx'9'zzz" );
	disp( fused( use_quotes, 'xxx\'{$x}\'zzz', x:'a' ) == "xxx'a'zzz" );
	disp( fused( use_quotes, 'xxx\'.{$x}.\'zzz', x:9 ) == "xxx'.9.'zzz" );
	disp( fused( use_quotes, 'xxx\'.{$x}.\'zzz', x:'a' ) == "xxx'.a.'zzz" );
	disp( fused( ' in ({$a}) ', a:[1,2] )  == " in (1,2) " );
	disp( fused( ' in ({$a}) ', a:[1,'a'] ) == " in (1,a) ");
	disp( fused( ' in ({$a}) ', a:[1,"'a'"] ) == " in (1,'a') ");
	disp( fused( 'in ({$a}) {$b}', {a:99, b: 'zzz' } ) == 'in (99) zzz');
	disp( fused( use_quotes, 'in ({$a})', a:[1,2] ) == 'in (1,2)');
	disp( fused( use_quotes, 'in ({$a})', a:[1,'A'] ) == 'in (1,\'A\')' );
	disp( fused( use_quotes, 'in ({$a})', a:['A','B'] ) == 'in (\'A\',\'B\')');
	disp( fused( use_quotes, 'in (\'{$a}\')', a:['A','B'] ) == 'in (\'A\',\'B\')');
	disp( fused( 'xxx\'{$a} {$b}\'zzz', a:'A', b:'B' )		== "xxx'A B'zzz");
	disp( fused( 'xxx\'{$a} {$b}\'zzz', {a:'A', b:'B'} ) 		== "xxx'A B'zzz");
	disp( fused( 'xxx\'{$a} {$b}\'zzz', ['a' => 'A', 'b' => 'B'] ) 		== "xxx'A B'zzz");
	disp( fused( use_quotes, 'xxx\'{$a} {$b}\'zzz', a:'A', b:'B' ) 		== "xxx'A B'zzz");
	disp( fused( use_quotes, 'xxx\'{$a} {$b} {$c}\'zzz', a:"'A'", b:'B', c:9 ) == "xxx'A B 9'zzz" );
	disp( ( hrtime(true) - $t0 ) / 1e6, 'ms');
	die;*/

split_on( string $s, int $i, int $d=0 ) ==> [ substr( $s, 0, $i), substr( $s, $i + $d) ];
split_csv( $s ) ==> {
	for $i = 0 .. strlen( $s ) - 1 {
		$q = null;
		case $s[$i] {
			'"': if ( $q = '"' ) $q = null; else $q ??= '"';
			"'": if ( $q = "'" ) $q = null; else $q ??= "'";
			',': if ( !$q ) $s[$i] = chr(130);
		}
	}
	return split( chr(130), $s );
 }
minv( $a, $b ) ==> min( is_bool($a) ? $b : $a, is_bool($b) ? $a : $b );
left( $s, $n ) ==> substr( $s, 0, $n );
right( $s, $n ) ==> substr( $s, -$n );
wildrx( $w ) ==> $w ? str_replace( '*', '.*', str_replace( '?', '.', $w ) ) : null;
bool: find( string $hay, string $needle, $from = 0, &$here = 0 ) ==> {
	if ( $from > strlen($hay) ) { $here = -1; return false; }
	if ( ( $here = strpos( $hay, $needle, $from ) ) === false ) { $here = -1; return false; }
	return true;
 }
bool: findrx( string $s, $rx, $i=0, &$o=0 ) ==> {
	if ( preg_match( $rx, $s, $m, offset:$i, flags: PREG_OFFSET_CAPTURE ) ){ $o = $m[0][1]; return true; } 
	$o = -1; return false;
 }

encode_named_params( ...$params) ==> {
	$out = '';
	$to_str($x)($to_str) ==> {
		case valtype( $x ) {
			'integer': return $x;
			'string': return "'{$x}'";
			'list'	: 
				$o = '';
				foreach( $x as $v ) $o .= $to_str( $v ) . ',';				
				return "[{$o}]";
			'record':
				$o = '';
				foreach( $x as $k => $v ) $o .= $k.':'.$to_str( $v ) . ',';				
				return "obj({$o})";
			'array':
				$o = '';
				foreach( $x as $k => $v ) $o .= "'{$k}' => " . $to_str( $v ) . ',';				
				return "[{$o}]";
			else: return /*'<i>' . valtype($x) . '</i>' .*/ print_r( $x, true );
		}
	};	
	if ( valtype( $params ) == 'list' ) return substr( $to_str( $params ), 1, -1);
	foreach ($params as $k => $v) {			
		$out .= $k . ':' . $to_str($v) . ',';
	}
	return $out;
}
decode_named_params( $p ) ==> {
	$x(...$p) ==> $p;
	return eval( 'return $x'."( $p );" );
}

overload str( array $a ) ==> {
	$s = 'Array(<div style="padding-left:20px;">';	
	$s .= join( ',<br>', array_map_keys( ($k,$v) ==> ' [' . $k .'] => '. str( $v ), $a ) );	
	$s .= '</div>)<br>';
	return $s;
 }
overload str( list $a ) ==> {
	$s = 'list[ ';
	$s .= join( ', ', array_map( 'str', $a ) );	
	$s .= ' ]';
	return $s;
 }
overload str( record $i ) ==> {
	$s = valtype( $i ) . '{<div style="padding-left:20px;">';
	foreach ($i as $k => $v ) $s .= $k . ': ' . str($v) . '<br>';
	$s .= '</div>}';
	return $s;
 }

parse_record( string $s ) ==> { //  { field: value, ... }
	$s = trim( str_replace( "'", "\'", $s ) );
	if ( $s[0] == '{' && $s[-1] == '}' ) $s = substr($s, 1, -1 );	
	if ( preg_match_all( '/[\n\r]\s*(\w+:)/', $s, $M, PREG_OFFSET_CAPTURE ) ) {
		$W = array_reverse( $M[1] );
		foreach( $W as $n => $m ) {
			for( $i = $m[1] + strlen( $m[0] ); $s[$i] <= ' '; $i++ );
			if ( $s[$i] != "'" && substr( $s, $i, 4 ) != 'obj(' ) {				
				$q = null;
				$b = 0;		
				for $j = $i .. $W[$n-1][1] ?? strlen($s)  {					
					case $s[$j] ?? ' ' {
						'"': if ( $q == '"' ) $q = null; else $q ??= '"';
						"'": if ( $q == "'" ) $q = null; else $q ??= "'";
						'(': if ( !$q ) $b++;
						')': if ( !$q ) $b--; if ($b < 0) goto do_quote;
						',': if ( !$q && !$b ) goto do_quote;						
					}
				}
				$j--;
				do_quote:
					for( $j--; $s[$j] <= ' '; $j--);					
					$s = substr_replace( $s, "'", $j+1, 0 );
					$s = substr_replace( $s, "'", $i, 0 );			
			}
		}
		//add commas if absent
		if ( preg_match_all( '/\'([\n\r\s]*\w+:)|\)([\s\n\r]*\w+:)/', $s, $M, PREG_OFFSET_CAPTURE ) ) {
			$MM = [];
			foreach ( $M[1] as $m ) if ( $m[1] > 0 ) $MM[] = $m;
			foreach ( $M[2] as $m ) if ( $m[1] > 0 ) $MM[] = $m;
			usort( $MM, ($a,$b) ==> $b[1] <=> $a[1] );
			foreach( $MM as $m ) $s = substr_replace( $s, ',', $m[1], 0);		
		}
	}
	//dispp($s);
	return eval( 'return obj( ' . $s . ');' );
 }

add_log( $s ) ==> { 
	if ( $s ) $s = json_encode( parse_record( fused( $s ) ) );
	file_put_contents( './daphnia_activity_' . date('Y-m-d') . '.log', 
		"\n". date('Y-m-d H:i:s') . ', ' . 
		$GLOBALS['me']->username . ', ' . 
		basename($_SERVER['PHP_SELF']).	', ' . 
		$var._action . ', ' . $s . ';', 
	FILE_APPEND | LOCK_EX);
 }

//--------- dap sqls ----------------
dap_sql_logic( $s ) ==> {  // ; , ~
	if ( find( $s, ';', 0, $i ) ) 
		return dap_sql_logic( substr( $s, 0, $i ) ) .' OR '. dap_sql_logic ( substr( $s, $i + 1 ) );
	
	if ( find( $s, ',', 0, $i ) ) 
		return dap_sql_logic( substr( $s, 0, $i ) ) .' and '. dap_sql_logic ( substr( $s, $i + 1 ) );

	if ( find( $s, '~', 0, $i ) ) 
		return '( ' . dap_sql_logic( substr( $s, 0, $i ) ) .' or '. dap_sql_logic ( substr( $s, $i + 1 ) ) . ' )';;
	return $s;
 }

dap_sql_hide_funcs( $s ) ==> {
	$s = trim( preg_replace( '/\bin\s+\(/', 'in(', $s ) , ','); 
	$n = [0];
	for $i = 0 .. strlen( $s )-1 {		
		case $s[$i] {
			'(': if ( $i > 0 && ctype_alpha( $s[$i-1] ) ) array_unshift( $n, 0 ); $n[0]++;
			')': $n[0]--; if( count($n) > 1 && $n[0] == 0 ) array_shift( $n ); 
			',', '=': if ( count( $n ) > 1 ) $s[$i] = chr( ord( $s[$i] ) | 128 );
		}
	}
	return $s;
 }

dap_sql_with( $s ) ==> {	
	$x = '';
	if ( findrx($s, '/\bselect\b/', 0, $i ) ) [$s,$x] = split_on( $s, $i );
	$x = trim( $x );
	$s = trim( substr( $s, 4) ) . ' ';	

	$W = [];	
	while ( findrx( $s, '/\bas\b/', 0, $i ) ) {
		for( $j = $i+2; $s[$j] <= ' '; $j++);
		if ($s[$j] == chr(129)) { //  xxx as ()
			for( ; $s[$j] > ' '; $j++);
			$t = trim( substr( $s, 0, $i-1 ), " \t\n\r\," );
			$q = trim( substring( $s, $i+2, $j), " \t\n\r\," );
			$W[] = "$t as $q";
			$s = substr( $s, $j ) . ' ';
		} else { //() as xxx(...)
			$n = 0;
			for( ; $s[$j] > ' '; $j++){
				case $s[$n] {
					'(': $n++;
					')': $j++; break 2;
					' ',',': if (!$n) break 2;
				}
			}
			$q = trim( substr( $s, 0, $i-1 ), " \t\n\r\," );
			$t = trim( substring( $s, $i+2, $j), " \t\n\r\," );
			$W[] = "$t AS $q";
			$s = substr( $s, $j );			
		}
	}
	$x = $x ? dap_sql( $x ) : ' select * from '.$t; 
	$x = "with\n" . join( ",\n", $W ) . "\n". $x;	
	return $x;
 }

dap_sql_update( $s0 ) ==> { //update table X set f1=v1, select/with/read v1 from ...,X				
	/*  tables being updated must be read in the select clause
		values used to update with must be selected in the select clause */
	$s = trim($s0);
	for $i = 0 .. strlen( $s )-1 { if ( $s[$i] < ' ' ) $s[$i] = ' '; }//tabs and CR/LF to space
	if ( str_starts_with( $s, 'update' ) ) $s = trim( substr( $s, 7) ); 
	if ( str_starts_with( $s, 'table' ) ) $s = trim( substr( $s, 6) ); else return $s0;

	$i = minv( rxpos( '/\bwhere\b/', $s ), rxpos( '/\bselect\b/', $s ),  rxpos( '/\bwith\b/', $s ) ); 
	[$u, $s] = split_on( $s, $i );

	if ( str_starts_with( $s, 'where' ) ) return 'update ' . trim($u,"\t\n\r ,") . ' ' . 
		unhide( dap_sql_logic( dap_sql_hide_funcs( trim( $s, "\t\n\r\ ," ) ) ) );

	$sql = dap_sql( $s );

	$i = rxposrev( '/\bselect\b/', $sql );

	$j = rxpos( '/\bfrom\b/', $sql, $i);
	$fields = [];
	foreach ( explode( ',', substring( $sql, $i+6, $j-1 ) ) as $f ) if ( $f = trim( $f ) ) {			
		$f = ' '.$f;
		$f = substr( $f, strrpos( $f, ' ')+1 );
		$f = '.'.$f;
		$f = substr( $f, strrpos( $f, '.')+1 );
		$fields[] = trim($f); 
	}

	$for_update = '';
	$loop_clause = '';
	$row_ids = '';
	$u = ' '.$u;
	//  table T1 set x=f table T2 z=ff
	$u = str_replace( ' set ', ' ', $u );
	foreach ( explode( ' table ', $u ) as $t ) if ( $t = trim($t) ) { 		
		[$tname, $set] = explode( ' ', $t, 2); $set = ltrim( $set );		
		$updates = [];
		foreach ( explode( ',', $set ) as $x ) if ( $x = trim($x) ) {
			[$f, $x] = split( '=', $x, 2 );
			$f = rtrim( $f );
			$x = ltrim( $x );
			$for_update .= ", {$tname}.{$f}";
			foreach( $fields as $ff) $x = preg_replace( "/\b$ff\b/", "REC_N.$ff", $x);
			$updates[] = $f.' = '.unhide( $x );
		}
		$loop_clause .= "\n\tUPDATE {$tname} SET \n\t\t" . join(",\n\t\t", $updates ) .
			"\n\tWHERE ROWID = REC_N.{$tname}_row_id;\n";
		$row_ids .= "\n   '' || {$tname}.ROWID AS {$tname}_row_id,";			
	}

	$sql = substr_replace( $sql, $row_ids . "\n\t", strrpos( $sql, 'select' ) + 6, 0);
	$for_update = "\nFOR UPDATE OF". substr( $for_update, 1).';';
	$loop_clause = "\n\nBEGIN \nFOR REC_N in CUR_1 LOOP\n" . $loop_clause . "\nEND LOOP; \nEND;";
	return "DECLARE CURSOR CUR_1 IS\n" . $sql 	. $for_update . $loop_clause;
 }			

dap_sql_insert( $s ) ==> {
	//insert into xxx named 'a' as x  
	//insert into xxx named select 'a' as x 
	//insert into table xxx named set x = 'a'  

	if ( preg_match_all( '/\b(into|named|table|set)\b/', $s, $M, PREG_OFFSET_CAPTURE ) ) { 
		$i = 0; $j = null; $t = 6;
		foreach ($M[0] as $m) {
			case $m[0] {
				'into':	 $t = $m[1] + 5; if ( $t > $i ) $i = $t;
				'table': $t = $m[1] + 6; if ( $t > $i ) $i = $t;
				'named': $j ??= $m[1]; if ( $m[1] > $i ) $i = $m[1] + 5; 
				'set':   $j ??= $m[1]; if ( $m[1] > $i ) $i = $m[1] + 3; 
			}
		}
		if (!$j) for( $j = $t; $s[$j] > ' '; $j++);
		$T = trim( substring( $s, $t, $j ) );			
		$ss = trim( substr( $s, $i ) );

disp(__line__, $T);
disp(__line__, $ss);
		if ( str_starts_with( $ss, 'select' ) ) $s = substr_replace( $s, dap_sql( $ss ), $i+6 );

		
		//if ( ( $k = strpos( $ss, 'from' ) ) !== false ) $ss = left( $ss, $k );
		if ( preg_match( '/\bfrom\b/', $ss, $F, PREG_OFFSET_CAPTURE ) ) {
			$ss = left( $ss, $F[0][1] );
		}
		
		$q = null; $n = 0;
		for $k = 0 .. strlen( $ss ) -1 {
			case ( $ss[$k] ) {
				"'", '"': $q = $q == $ss[$k] ? null : $ss[$k];
				'(': if (!$q) $n++;
				')': if (!$q) $n--;
				',': if (!$q && $n == 0) $ss[$k] = "\x00";
			}				
		}
		$K = $V = [];
		foreach( splitt( "\x00", $ss ) as $kv ) {
			if (str_contains( $kv, '=' ) ) [$k,$v] = splitt( '=', $kv, 2 );
			else [$v,$k] = splitt( '/\b[aA][sS]\b/', $kv, 2 );
			if (!$k) [$v,$k] = splitt( '/\s(?=[^\s]*$)/', $kv, 2 ); 
			$K[] = $k; $V[] = $v;
		}
		
		if ( str_starts_with( $ss, 'select' ) ) {
			$ss = '(' . join(", ", array_filter($K) ) . ') ';			
			//$s = substr_replace( $s, $ss, $i, 6 );
			$s = 'insert into ' . $T . " " . $ss . "\n" . substr( $s, $i+6 );
		} else {
			$ss = '(' . join(", ", array_filter($K) ) . ")\n values (\n\t" . join( ",\n\t", $V ) . ')';				
			$s = 'insert into ' . $T . " " . $ss;
		}				
	}
	return $s;
 } 
	/*
		dispp( dap_sql_insert( 'insert into xxx named 1 as x, 2 as y,3 as z') );
		dispp( dap_sql_insert( 'insert into xxx named select 1 as x, 2 as y, 3 as x from zzz where zzz.id = 1 ' ) );
		dispp( dap_sql_insert( 'insert table xxx set x = \'a\'  ' ));
		die;
	*/

dap_sql( $s ) ==> {	
	$s = trim($s);

	//hide quoted strings
		$q = null;
		for $i = 0 .. strlen( $s )-1 {
			case $s[$i] {
				'"': if ( $q == '"' ) $q = null; else $q ??= '"';
				"'": if ( $q == '"' ) $q = null; else $q ??= '"';
				else: if ($q) $s[$i] = chr( ord( $s[$i] ) | 128 );
			}		
		}
		$s = strtolower( $s );

	//@SQLfunction calls
	// ( select description from ( @GENCOD( code:paper_type_gc22, category:22 ) ) ) as assess_type2, .........	
		if ( preg_match_all( '/\@\w+\(/', $s, $M, PREG_OFFSET_CAPTURE ) ) {
			foreach( array_reverse( $M[0] )  as $m ) {
				$i = $m[1];
				$j = str_paired( $s, $i );
				$ss = substring( $s, $i+1, $j );				
				[$f, $ps] = splitt( '(', $ss, 2 );								
				$P = [];
				foreach ( split_csv( substr( $ps, 0, -1) ) as $kv ) {
					$pp = splitt( ':', $kv, 2 );
					$P[$pp[0]] = '\\' . $pp[1];
				}				
				$sql = SQL( $f, ...$P );
				if ( isset( $sql.sql ) ) $sql = $sql.sql;
				$s = substring_replace( $s, $sql, $i, $j );				
			}
		}

	//subqueries: ( select / ( with
		if ( findrx( $s, '/\([\s\n\r]*(select|with)\b/', 0, $i ) ) {
			$j = str_paired($s, $i);
			$ss = substring( $s, $i+1, $j-1 );
			$ss = dap_sql( $ss );
			$s = substring_replace( $s, chr(129) . $i , $i, $j );
			$s = dap_sql( $s );
			$s = str_replace( chr(1) . $i, "($ss)", $s );
			return unhide( $s );
		}

	//with
		if ( str_starts_with( $s, 'with' ) ) return unhide( dap_sql_with( $s ) );

	//update/insert
	//insert/update
	if ( str_starts_with( $s, 'update/insert' ) || str_starts_with( $s, 'insert/update' ) ) {
		$s = ltrim( substr( $s, 13 ) );
		if ( str_starts_with( $s, 'table' ) ) $s = ltrim( substr( $s, 5 ) );
		for( $i = 0; $s[$i] > ' '; $i++);
		[$T,$s] = split_on( $s, $i );		

		/*
		insert/update table staff
			name = 'Harry'
		where
			persno = 1113216
		*/

		$U = $s;
		[ $vals, $idxs ] =  split( '/\bwhere\b/', $s);
		$I = $idxs . ',' . $vals;
		$s = "insert table $T select $I or else update table $T set $U";	
disp( __line__, $s );		
	}

	// insert OR ELSE update
		if ( preg_match( '/\bor\s+else\b/', $s, $M, PREG_OFFSET_CAPTURE ) ) {		
			[$L,$R] = split_on( $s, $M[0][1], strlen($M[0][0]) );
			if ( str_starts_with( $R, 'insert' ) ) [$R,$L] = [$L,$R];
			[$L, $R] = [ unhide( dap_sql_insert( $L ) ), unhide( dap_sql_update( $R ) ) ]; 							
			if ( $L[-1] != ';' ) $L .= ';';
			if ( $R[-1] != ';' ) $R .= ';';
			return "BEGIN\n {$L} \nEXCEPTION WHEN DUP_VAL_ON_INDEX THEN\n {$R} \nEND;";
		}		

	//update
		if ( str_starts_with( $s, 'update' ) ) return unhide( dap_sql_update( $s ) );

	//insert
		if ( str_starts_with( $s, 'insert' ) ) return unhide( dap_sql_insert( $s ) );

	//delete
		if ( str_starts_with( $s, 'delete' ) ) return unhide( dap_sql_logic( dap_sql_hide_funcs( $s ) ) );

	//union
		if ( findrx( $s, '/\bunion\b/', 0, $i ) ) {
			$ls = substr( $s, 0, $i );
			$rs = trim( substr( $s, $i+5 ) );
			$all = '';
			if ( str_starts_with( $rs, 'all' ) ) {
				$all = 'all ';
				$rs = substr( $rs, 3 );
			}
			return unhide( dap_sql( $ls ) . "\nunion {$all}\n" . dap_sql( $rs ) );
		}

	// over( partition by ... order by ...)
	// within group( order by ... )	
		if ( preg_match_all( '/\bover\s*\(/', $s, $M, PREG_OFFSET_CAPTURE ) ) {	
			foreach ( $M[0] as $m ) {
				for $i = $m[1] .. str_paired( $s, $m[1] ) {
					if ($s[$i] == 'o') $s[$i] = chr( ord( $s[$i] ) | 128 );
				}				
			}
		}		
		if ( preg_match_all( '/\bwithin\s+group\s*\(/', $s, $m, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $M[0] as $m ) {
				for $i = $m[1] .. str_paired( $s, $m[1] ) {
					if ($s[$i] == 'o') $s[$i] = chr( ord( $s[$i] ) | 128 );
				}				
			}
		}	

	//throw 'invalid'
		if ( str_starts_with( $s, 'throw' ) ) {				
			if ( ( $i = strpos( $s, 'where' ) ) !== false ) 
				$s = 'select ' . substring( $s, 6, $i-1) . ' as "_error_message" from dual '.
				 substr( $s, $i );
			else
				$s = 'select ' . substr( $s, 6) . ' as "_error_message" from dual';
			return unhide( $s );
		}

	//split SELECT into parts
		$parts = [];
		$s = preg_replace( '/\b(throw|select|from|join|where|group\s+by|order\s+by|fetch|limit)\b/', chr(128) . '$1', $s );
		foreach( splitt( chr(128), $s ) as $ss ) {
			$ss = trim( $ss, " \n\r\t" );
			for( $i = 0; ctype_alpha($ss[$i]); $i++); //i past end of command
			if ($i) {
				$P = substr( $ss, 0, $i );			
				$p = trim( substr( $ss, $i), " \n\r\t," );
				if ( $P == 'join' ) {
					$parts[ $P ] ??= [];
					$parts[ $P ][] = $p;
				} else {			
					$parts[ $P ] ??= '';
					$parts[ $P ] .= ',' . $p;
				}
			}
		}
		if ( isset( $parts['throw'] ) ) $parts['select'] = $parts['throw'] . ' as "_error_message"';
	
	//hide commas in function parameter declarations in logic clauses
		if ( $parts[ 'where' ] ?? false  ) {			
			$parts[ 'where' ] = dap_sql_logic( dap_sql_hide_funcs( $parts[ 'where' ] ) );
		} else $parts[ 'where' ] = '';

	//join[+-*] table on conditions / join table using( field, field... )
		//join+ left outer - all from left plus whereever right matches
		//join- right outer - only mathing lefts, and all rights
		//join* full outer - allrecords from both
		//join 
		$J = '';
		if ( count( $parts['join'] ?? [] ) ) {	
			foreach( $parts['join'] ?? [] as $j => $s ) {
				$i = array_first( scanrx( $s, '/\b(on|using)\b/' )[0] );			
				if ( $s[$i] == 'o' ) $s = dap_sql_logic( dap_sql_hide_funcs( $s ) );
				case $s[0] {
					'+': $s = "\n left outer join ". substr( $s, 1 );
					'-': $s = "\n right outer join ". substr( $s, 1 );
					'*': $s = "\n full outer join ". substr( $s, 1 );
					else: $s = "\n join ". substr( $s, 1 );
				}
				$J .= $s;
			}
		}
		$parts['join'] = $J;

	//fix equate-notation to alias-notation
		$s = dap_sql_hide_funcs( $parts['select'] ) ;
		$ss = splitt(',', $s );
		foreach( $ss as $k => $v ) {
			if ( ( $j = strpos( $v, '=' ) ) !== false ) {
				for( $i = 0; $v[$i] <= ' '; $i++ );
				$ss[$k] = substr( $v, 0, $i ) . substr( $v, $j+1 ) . ' as ' . substr( $v, $i, $j );
			}
		}
		$parts['select'] =  join( ",", $ss );

	//"{name} {title}"  interpolating current record into selected fields
		$s = unhide( $parts['select'] ?? '');
		if ( preg_match_all( '/\"[^"{]*\{\w*\}[^"]*\"/', $s, $M, PREG_OFFSET_CAPTURE ) ) {
			foreach ( array_reverse( $M[0] ) as $m ) {
				$i = $m[1];
				$j = $i + strlen( $m[0] ) - 1;			
				$ss = substring( $s, $i+1, $j-1 );
				$ss = str_replace( '{', '\' || ', $ss );
				$ss = str_replace( '}', ' || \'', $ss );			
				$s = substring_replace( $s, "'$ss'", $i, $j );
			}
			$parts['select'] = $s;
		}

	if ( isset( $parts['limit'] ) ) {
		$parts['fetch'] = "\n fetch first " . trim( $parts['limit'], ',') . ' rows only';
	} elseif ( isset( $parts['fetch'] ) ) {
		$parts['fetch'] = "\n fetch " . trim( $parts['fetch'], ", \n\r\t");
	} else {
		$parts['fetch'] = '';
	}

	//recombine SELECT into sql
		if ( ! isset( $parts['select'] ) ) return '';
		if ( isset( $parts['from'] ) ) $parts['from'] = "\n from ". trim( $parts['from'], ','); 
		else $parts['from'] = "\n from dual";
		$parts['where'] = $parts['where'] ? "\n where ". $parts['where'] : ''; 
		$parts['group'] = isset( $parts['group'] ) ? "\n group ". trim( $parts['group'], ',') : ''; 
		$parts['order'] = isset( $parts['order'] ) ? "\n order ". trim( $parts['order'], ',') : ''; 
		$s = 'select ' . trim( $parts['select'], ',') . 
			$parts['from'] . $parts['join'] . $parts['where'] . $parts['group'] . $parts['order'] . 
			$parts['fetch'];

	return unhide( $s );
 }

add_http_params( $s, $P ) ==> {
	$p = [];
	foreach( $P as $k => $v ) $p[] = $k . '=' . urlencode( $v );
	return $s . ( str_contains( $s, '?' ) ? '&' : '?' ) . join( '&', $p );
 }

//----------------

getHTMLAttribute( $html, $att ) ==> {
	$i = scanrx( $html, '/\b'.$att.'=/' )[0] ?? false;
	if ( $i === false ) return '';
	for(; isset( $html[$i] ) && $html[$i] <> '=' && $html[$i] <> '>'; $i++);
	if ($html[$i] <> '=') return true;
	$i++;
	if ($html[$i] == '"') { 
		$i++;  //get first non escaped '"'
		for( $j = $i; isset( $html[$j] ) && $html[$j] != '"'; $j++) if ( $html[$j] == '\\' ) $j++;
	} elseif ($html[$i] == "'") { 
		$i++;  //get first non escaped "'"
		for( $j = $i; isset( $html[$j] ) && $html[$j] != "'"; $j++) if ( $html[$j] == '\\' ) $j++;
	} else {
		for( $j = $i; isset( $html[$j] ) && $html[$j] > ' ' && $html[$j] <> '>'; $j++);
	}
	return substring( $html, $i, $j-1 );
 }

label(...$label) ==> {
	$T = templates( 'label' );
	$T.label = $label['label'] ?? $label[0];
	case ( $label['opt'] ?? false ) {
		'key': $T.tags = 'class=key';
		'mandatory','required','needed': $T.tags = 'class=mandatory';
	}
	return fused( $T.html, $T );
 }

readonly( ...$ro ) ==> {	
	$T = templates( 'readonly_input' );
	$T.name = $ro['name'] ?? $ro[0];
	$T.label = $ro['label'] ?? '';
	$T.tags = $input['tags'] ?? '';
	if ( isset( $var->_row ) ) {
		$T.value = $_POST[ $T.name ] ?? $var->{$T.name} ?? null;
		$T.name_r = $T.name . '_' . $var->_row;
		$T.tags .= ' tablename=' . $var._table_name;
		$T.tags .= ' row=' . $var->_row;
	} else {
		$T.value = $_POST[ $T.name ] ?? $var->{$T.name} ?? $_SESSION[ $name ] ?? null;
		$_SESSION[ $name ] = $T.value;		
		$T.name_r = $T.name;
	}
	$_SESSION[$_instance][ 'readonly_fields' ][ $var->_row ?? 0 ][] = [$T.name, $T.value]; 
	return fused( $T.html, $T );
 }

/** input()
	inut( name: { attr:value ... }
	inut( name: [ attr => value ... ]
	input( 'name', [] / {}
		
	input defaults are shared over all apps
	output defaults are not

	attr:= 
		name
		type=text/par/textarea/flag/yesno/link/href/url/aling/button/list/boxpicker/picke/number/readonly/hidden/
			datetime/date/file
		minmax
		params
		sql
		options
		multi=yes/no
		tags
		allow
		filter
		size
		max_size
		label
		rows
		cols
		html
		validate
		opt=key/mandatory
		hint
**/
enum {
	key,
	eRequired,	
}
input( ...$input ) ==> { 	
	if ( count( $input ) == 1 ) {
		$k1 = array_key_first( $input );
		if ( valtype( $input[$k1] ) == 'record' ) $input = [ $k1, ... (array) $input[$k1] ];							
		elseif ( valtype( $input[$k1] ) == 'array' ) $input = [ $k1, ... $input[$k1] ];				
	}
	if ( valtype( $input ) == 'record' ) $input = (array) $input;

	foreach( $input as $k => $v ) if ( valtype( $v ) =='closure' ) $input[$k] = $v();

	$type = $input['type'] ?? 'text'; unset( $input['type'] );	

	if ( str_starts_with( $type, 'par' ) ) $type = 'textarea';
	if ( $type == 'flag' ) $type = 'yesno';
	if ( $type == 'link' ) $type = 'url';
	if ( $type == 'href' ) $type = 'url';

	if ( $type == 'radio' ) $type = 'picker';
	if ( $type == 'checkbox' ) $type = 'boxpicker';

	if ( $type == 'alink' ) {
		$input[0] = $var->{$input[0]};
		if ( isset( $input['params'] ) && valtype( $input['params'] ) == 'closure' ) 
			$input['params'] = $input['params']();
		return alink( ...$input );
	}
	if ( $type == 'button' ) {
		return button(...$input);
	}

	$multi = in_array( $input['multi'] ?? null, ['yes', 'on', 'true', true ] );

	if ( ( isset( $input['options'] ) || isset( $input['sql'] ) ) && ( $type == 'text' ) ) {

		if ( isset( $input['sql'] ) ) $type = 'list';
		elseif ( isset( $var->_row ) ) $type = 'list';
		elseif ( $multi ) $type = 'boxpicker'; 
		else $type = 'picker';
	}
	
	if ( $type !='readonly' && isset( $input['minmax'] ) ) $type = 'number'; 

	$T = templates( $type . '_input' );
	if (!$T) die ( '<font color=red>ERROR: unknown template:' . $type . '_input');
	if ( is_string( $T ) ) $T = {html: $T};

	/*
	The type: indicate the template containing the settings, or refer to another template for it.
	The refered template's settings are then overridden with the indicated template's, 
	which is overriden with app input (except for 'type' itself).
	*/

	if ( isset( $input[0] ) ) { $input['name'] = $input[0]; unset( $input[0] ); }
	$T = forge( $T, $input );	
	
	if ( isset( $input['format'] ) ) $T = forge( $T, $input['format'] );	
		
	if ( ( $locked[ $T.name ] ?? false ) && $type <> 'readonly' ) {
		$input[ 'type' ] = 'readonly';				
		return input( ...$input );
	}		
	$T.tags ??= '';	
	$T.allow ??= 'a-zA-Z0-9 ';
	$T.filter ??= '[a-zA-Z0-9 ]*'; 
	$T.size ??= 30;
	$T.max_size ??= $T.size;
	$T.label ??= '';
	if ( isset( $T.minmax ) ) { 
		$mm = split( str_contains($T.minmax, '-') ? '-' : '..', $T.minmax ); 
		$T.tags .= ' min="' . $mm[0] . '" max="' . $mm[1] .'" ';
		$T.size = strlen( $mm[1] );
	}
	if ( str_contains( $T.size, '-' ) ) {
		[$T.size, $T.max_size] = split( '-', $T.size );
	}

	if ( str_contains( $T.size, 'x' ) || $type == 'textarea' ) {
		$T.filter = '.*';
		[$T.rows, $T.cols] = split( 'x', $T.size );
		$T.html = templates( 'textarea_input' )->html;
	}
	if ( isset( $T.hint ) ) $T.tags .= ' title="' . $T.hint . '"';
	if ( isset( $T.example ) ) $T.tags .= ' placeholder="' . $T.example . '"';

			
	//input defaults are shared over all apps
	$fname = $input['data_field'] ?? $T.name;
	if ( isset( $var->_row ) ) {		
		$T.value = $T.VALUE ?? $_POST[ $T.name ] ?? $var->{$fname} ?? $T.value ?? null;
		$T.name_r = $T.name . '_' . $var->_row;
		$T.tags .= ' tablename="' . $var._table_name . '"';
		$T.tags .= ' row="' . $var->_row . '"';
	} else {
		$T.value = $T.VALUE ?? $_POST[ $T.name ] ?? $var->{$fname} ?? $_SESSION[ $T.name ] ?? $T.value ?? null;
		$T.tags .= ' tablename="' . $var._table_name . '"';
		if ( ! $T.value ) {
			$carry = basename( $GLOBALS['_filename_'], '.php') . '.' . $T.name;			
			if ( isset( $_SESSION[$carry] ) ) { 
				$T.value = $_SESSION[$carry];
				unset( $_SESSION[$carry] );
			}	
		}
		$_SESSION[ $T.name ] = $T.value;		 
		$T.name_r = $T.name;
	}

	if ($type == 'number') {
		$T.allow ='0-9';
		if ( ( $d = $input['decimals'] ?? false ) ) {			
			$T.allow ='0-9.';
			$T.tags .= ' decimals=' . $d;
			$T.filter = '^[0-9]*(\.\d{0,' . $d . '})?$';
		}
	}
	
	if ( $type == 'readonly' && isset( $T.options )  && isset( $T.options[ $T.value ?? '' ] ) ) {
		$T.value .= ' - ' . $T.options[ $T.value ]; 
	}

	if ( $type == 'datetime' ) {
		if ( $T.value ) {
			$T.value = substr( $T.value, 0, 16);
			$T.dtvalue = $T.value;
			if (strlen( $T.value ) > 10 ) $T.value[10] = 'T';
			if (strlen( $T.value ) > 13 ) $T.value[13] = ':';	
			$T.allow = "0-9 :-";
			$T.filter = '.*';		
		}
	}
	if ( $type == 'date' ) { 
		if ( $T.value ) {
			$T.value = substr( $T.value, 0, 16);
			$T.value[10] = 'T';
			$T.value[13] = ':';
		}
	}

	if ( $input[ 'validate' ] ?? false ) 
		$T.tags .= ' validate="' . base64_encode( $input[ 'validate' ] ) . '"';

	$var->_table_name ??= 'output';

	case ( $T.opt ?? false ) {
		key: $T.tags .= ' class=key';
		eRequired, 'needed','required','mandatory': $T.tags .= ' class=mandatory';
	}	

	if ( ! isset( $T.html) ) die('<font color=red>ERROR: Template html empty: '.$type); 

	if ( isset( $input['options'] ) && valtype( $input['options'] )  == 'string' ) {
		$input['sql'] ??= $input['options'];
		$input['options'] = [];
	}

	$option_hint = [];
	if ( isset( $input['sql'] ) ) {
		$sql = $input['sql'];		
		$options = get_data( LOCAL, $input['sql'] );
		$input['options'] ??= [];
		if ( $options.row_count > 5 && $type != 'list') {
			$type = 'list';
			$T = forge( $T, templates( $type . '_input' ));
		}
		if ( $options.err ) { disp( $options ); die; };
		if ( $options.row_count > 0 ) {
			$k = array_keys( $options->data[0] );
			foreach( $options->data as $R ) {
				$c = $R[ $k[0] ];
				$d = $R[ $k[1] ];
				$h = isset( $k[2] ) ? $R[ $k[2] ] : '';
				$input['options'][ $c ] = $d; 					
				$option_hint[$c] = $h;
			}
		}
	}

	if ( $type == 'file' ) {
		$T.value = '';
	}

	if ( is_object( $input['options'] ?? false ) ) $input['options'] = (array) $input['options'];	
	
	if ( $type == 'yesno' ) { 	
		$T.checked = in_array( $T.value ?? false, [1,'checked','yes','Y'] ) ? ' checked ' : '';
		$T.value = $T.checked ? 'Y' : 'N';
	}

	if ( $type == 'picker' ) { // options: { code:value, code,value }		
		$T.options = '';
		foreach ( $input['options'] as $code => $label ) {
			$T.checked = ($T.value == $code) ? ' checked ' : ' ';
			$T.multi = $multi ? 'true' : 'false';
			$T.code = $code;
			$T.label = $label;
			$T.options .= fused( templates( $type . '_input_option' )->html, $T );
		}		
	}

	if ( $type == 'boxpicker' ) { // options: { code:value, code,value }		
		$T.options = '';
		foreach ( $input['options'] as $code => $label ) {
			$vv = is_string( $T.value ) ? split('+', $T.value ) : [$T.value];
			$T.checked = in_array( $code, $vv ) ? ' class=boxed ' : ' ';
			$T.multi = $multi ? 'true' : 'false';
			$T.code = $code;
			$T.label = $label;
			$T.options .= fused( templates( $type . '_input_option' )->html, $T );
		}		
	}
	
	if ( $type == 'list' ) {
		$T.options = '';		
		$value = is_array( $T.value ) ? $T.value : [ $T.value ];
		$cf = false;	
		foreach ( $input['options'] as $code => $label ) {	
			if ( valtype( $label ) != 'string' ) $label = str( $label );
			// options:['a','b','c'] vs [0=>'a',1=>'b',2=>'c']
			// if ( valtype( $input['options'] ) == 'list' ) $code = $label;		
			$T.checked = in_array( $code, $value ) ? ' selected ' : '';
			if ( $label[0] == '>' ) $T.checked = ' selected ';
			$T.multi = $multi ? ' multiple ' : ' ';
			$T.code = $code;
			$T.label = ( $T.checked ? '&gt;' : '' ) . $label;
			$T.hint = $option_hint[ $code ] ?? '' ;
			$T.options .= fused( templates( $type . '_input_option' )->html, $T );
			if ( $T.checked ) $cf = true;
		}
		if ( !$cf && count($value ?? []) > 0 && $value[0] ) { //the field contains a value not in the options
			$T.checked = ' selected ';			
			$T.multi = $multi ? ' multiple ' : ' ';
			$T.code = $value[0];
			$T.label = '&gt;' . $value[0];
			$T.options .= fused( templates( $type . '_input_option' )->html, $T );
		}
	}

	if ( isset( $input['options'] ) ) {			
		$T.filter = join('|', array_keys( $input['options'] ) );
		$T.allow = join('', array_keys( $input['options'] ) );
	}

	published $used_fieldnames ??= [];
	if ( isset( $used_fieldnames[$T.name] ) && ( $var._row ?? 1 ) <= 1 ) {
		disp('<font color=red>Error: duplicate field names:' . $T.name . 
			' in ' . $used_fieldnames[$T.name] . ' and ' . $var._table_name . '</font>');
	}
	$used_fieldnames[ $T.name ] = $var->_table_name;

	$_SESSION[$_instance][ 'input_fields' ][$T.name] = [ 
		'filter' => $T.filter, 
		'allow' => $T.allow, 
		'row' => $var._row ?? null,
		'table' => $var->_table_name,
	];
	
	return fused( $T.html, $T ) . ( $type != 'hidden' ? '<error id="error_' . $T.name_r . '"></error>' : '' );	
 }

table( array $body, $tags='', $tr_tags='', $td_tags='' ) ==> {
	$out = "<table border=0 $tags>";
	$m = 0;
	foreach( $body as $row ) {
		$m = max( $m, count($row) );	
	}
	foreach( $body as $row ) {
		$out .= "<tr $tr_tags>";
		$n = count($row);
		foreach( $row as $cell ) {
			$n--;
			if ($n == 0 && $m - count($row) > 0 ) {
				$out .= '<td valign=top nowrap colspan='.($m - count($row) + 1 )."  $td_tags>";
			} else $out .= "<td valign=top nowrap  $tr_tags>";
			$out .= $cell;
			$out .= '&nbsp;</td>';
		}
		$out .= '</tr>';
	}
	$out .='</table>';
	return $out;
 }

/** buttons()
	buttons( open: {}, close: {}, {name:...} )
**/
buttons( ...$B ) ==> { 
	$out = '';
	foreach ( $B as $k => $b ) if ($b) {
		if ( valtype( $k ) == 'string' ) $out .= button( (object)[ $k => $b ] );					
		else $out .= button( ...(array)$b );
	}
	return $out;
 }

/** button/alink
	app/url/report
	params
	action
	output
	tags
	confirm
	prompt
**/
ctrl( $T, $settings ) ==> {
	$T = forge( $T, $settings );	

	$T.tags ??= '';	
	$T.app ??= null;	//page name
	$T.url ??= null;	//http
	$T.report ??= null; //declarative
	$T.action ??= null; //declarative
	$T.output ??= null; //declarative
	
	$T.tags .= ' tablename="' . ($var._table_name ?? '') . '"';
	$T.tags .= ' row="' . ($var->_row ?? '') . '"';
	
	$vc = ($T.url ? 10 : 0) + ($T.app ? 10 : 0) + ($T.report ? 10 : 0);
	if ( $vc > 10 )  die("Daphnia: ERROR - {$T->label}: a control may only have one app, url or report setting");
	$vc += ($T.action ? 1 : 0) + ($T.output ? 1 : 0);
	if ( $vc > 10 )  die("Daphnia: ERROR - {$T->label}: an app, url or report control may not have an action or output setting");

	$alert = $event = $open = '';

	if ( isset( $T.url ) ) { // non daphnia web page	
		if ( isset( $T.params ) ) $T.url .= add_http_params( $T.url, $T.params );
		$open = "window.open('" . $T.url . "' ); return false;";
		goto done;
	}
	
	$T.cmd = str_replace( ' ', '_', $settings[0] ?? $T.label );
	$var.app = $T.app; $var.report = $T.report; $var.output = $T.output; $var.action = $T.action;

	//needed for alink, but breaks buttons...  
	if ( $settings['_type'] == 'alink' && $vc < 10 ) $T.app ??= $_SERVER['PHP_SELF'];	

	if ( $T.app || $T.report ) {

		//store the data space in a unique identifier
		$args = rand() . rand() . rand();			
		$_SESSION[ 'args' ][ $args ] = serialize( [ 'to' => time() + $app_timeout, 
			'vars' => $var ?? [], 
			'params' => $T.params ?? [],
		] );

		if ( $T.app ) { 			
			$T.url = $T.app;
			$open = "postToNewWindow( '" . $T.app . "', '" . $args . "' ); return false;";
			goto done;
		}	

		if ( $T.report ) { 		
			$T.report = basename( $GLOBALS['_filename_'] );
			$open = "postToNewWindow( '" . $T.report . "', '" . $args . "' ); return false;";
			goto done;
		}
	}

	instance('command_actions[]') = [$T.cmd => $var.action];
	instance('command_output[]' ) = [$T.cmd => $var.output];
	instance('command_report[]' ) = [$T.cmd => $var.report];
	if ($T.state ?? false) {
		instance('command_state[]') = [$T.cmd => $T.state];
	}
	
	if ( in_array( $T.confirm ?? 'on', ['on','true','yes' ] ) ) 
		$alert = "if ( _id('_this_ochanged').value && !confirm('Changes will be lost, continue?')) return false;";
	elseif ( in_array( $T.confirm ?? 'on', ['off','false','no' ] ) )
		$alert = '';
	elseif ( $T.confirm ?? false ) 			
		$alert = "if ( !confirm('" . $T.confirm . "')) return false;";	

	if ( isset( $T.prompt ) ) {
		$alert .= '_id(\'_commandPrompt\').value = prompt(\'' . $T.prompt .'\');' ;
	}
	
	if ( $var.action ) $event .= 'if (! do_ajax( this ) ) return false;';
	$event .= $var.output? 'return submitWith(this);' : 'return false;';

	if ( ($T.action ?? false) ) $T.tags .= ' do_validations=True'; // format: {validate:'$field# > 0'}
	
	done:
	$T.onclick = $alert . $event . $open;
			
	return fused( $T.html, $T  );
 }

button( ...$settings ) ==> { //see ctrl()	
	if ( count( $settings ) == 1 ) {
		if ( valtype( $settings )  == 'list' ) $settings = (array)$settings[0];
		$k1 = array_key_first( $settings );
		if ( valtype( $settings[$k1] ) == 'record' ) $settings = [ $k1, ... (array) $settings[$k1] ];							
		elseif ( valtype( $settings[$k1] ) == 'array' ) $settings = [ $k1, ... $settings[$k1] ];			
	}
	$settings['_type'] = 'button';
	$settings['label'] ??= $settings[0] ?? 'submit';
	return ctrl( templates('button'), $settings);	
 }

alink( ...$settings ) ==> { //see ctrl()
	if ( count( $settings ) == 1 ) {		
		$k1 = array_key_first( $settings );
		if ( valtype( $settings[$k1] ) == 'record' ) $settings = [ $k1, ... (array) $settings[$k1] ];							
		elseif ( valtype( $settings[$k1] ) == 'array' ) $settings = [ $k1, ... $settings[$k1] ];			
	}
	$settings['_type'] = 'alink';
	$settings['label'] ??= $settings[0] ?? 'go';
	return ctrl( templates('alink'), $settings);	
 }

menu( $Menu ) ==> { 
	
	$out = '';

	foreach( $Menu ?? [] as $label => $settings ) {
		
		$T = templates('menu');	
		$T = forge( $T, $settings );		
		$T.tags ??= '';
		$T.label ??= $label;	
		
		if ( isset( $T.hint ) ) $T.hint = 'title="' . $T.hint . '"';

		if ( isset( $T.url ) ) { // non dapnia page	
			if ( isset( $T.params ) ) $T.url .= add_http_params( $T.url, $T.params );
			$T.onclick = "window.open('" . $T.url . "' ); return false;";
			$out .= fused( $T.html, $T );
			continue;
		}

		$T.cmd = str_replace( ' ', '_', $T.label );

		$var.action = $T.action ?? '';
		$var.output = $T.output ?? '';
		$var.report = $T.report ?? '';	

		instance('command_actions[]') = [$T.cmd => $var.action];
		instance('command_output[]' ) = [$T.cmd => $var.output];
		instance('command_report[]' ) = [$T.cmd => $var.report];

		$args = rand() . rand() . rand();
		if ( isset( $T.app )  ) $atype = 'app';
		elseif ( isset( $T.report ) ) $atype = 'report';
		elseif ( isset( $T.action ) ) $atype = 'action';
		elseif ( isset( $T.output ) ) $atype = 'output';
		else $atype='app';	
		$_SESSION[ 'args' ][ $args ] = serialize( [ 'to'=> time() + $app_timeout, 
			'type' => $atype,
			'vars' => $var ?? [], 
			'params' => $T.params ?? [],
		] );	

		if ( isset( $T.app ) ) { 
			$T.app = add_http_params( $T.app, { args: $args } );
			$T.onclick = "window.open('" . $T.app . "' ); return false;";
			$out .= fused( $T.html, $T );
			continue;
		}	

		if ( isset ( $T.report ) ) { 		
			$T.app = basename( $GLOBALS['_filename_'] );
			$T.app = add_http_params( $T.app, { args: $args } );
			$T.onclick = 'window.open(\'' . $T.app . '\');return false;';
			$out .= fused( $T.html, $T );
			continue;
		}		

		$T.confirm ??= 'off';
		if ( in_array( $T.confirm, ['on','true','yes' ] ) ) 
			$T.onclick = "if ( _id('_this_ochanged').value && !confirm('Changes will be lost, continue?')) return false;";
		elseif ( in_array( $T.confirm, ['off','false','no' ] ) ) 
			$T.onclick = "";
		else
			$T.onclick = "if ( !confirm('" . $T.confirm . "')) return false;";
		
		//if no action 
		if ( !($T.action ?? false) ) $T.tags.= ' do_validations=True';

		$out .= fused( $T.html, $T );		
	}
	
	return $out;
 }

//--------------

$out = null;

perform_actions_events ==> {
	global $me, $out;
	if ( isset( $out.err ) && $out.err ) return;
	foreach( $_POST as $cmd => $v ) if ( str_starts_with( $cmd, 'command_' ) ) {
		$F = instance( 'command_actions' )[ substr( $cmd, 8) ] ?? null;
		foreach( split( ',', $F ) as $fn ) if ($fn) { 
			$var->_action = $fn;
			$fname = split( '(', $fn );
			if ( function_exists( $fname[0] ) ) {
				if ( count($fname) > 1 ) $out = eval( 'return ' . $fn .';');
				else $out = $fname[0]();
				if ( $fn == 'logout' ) { echo "<script language=javascript> location.reload(); </script>"; die; }
				if ( isset( $out.err )  && $out.err ) {					
					disp( 'err:::', $out.err ?? '' );
					if ( appSettings->debug->sql ?? false ) disp( '<br>', $out.sql ?? '' );
					disp( ':::<p>' );					
					return;
				}
			}
			else disp('err::: ERROR: Action not found: '.$fn.':::');
		} //else  disp('<font color=red>ERROR: Action not found:'.substr($cmd, 15).'()</font>');
	}
 }

perform_output_events ==> {
	global $me, $out;
	if ( isset( $out.err )  && $out.err ) return;
	$the_output .= '<table border=0 style="padding-left:20px;"><tr><td>';
	foreach( $_POST as $cmd => $v ) if ( str_starts_with( $cmd, 'command_' ) ) {
		$F = instance( 'command_output' )[ substr( $cmd, 8) ] ?? null; 
		foreach( split( ',', $F ) as $fn ) if ( $fn ) { 
			if ( function_exists( $fn ) ) {
				$out = $fn();
				if ( isset( $out.err ) && $out.err ) {
					write( '<font color=red>' . $out.err . '</font>' );
					goto done; 
				}
			}
			else disp('<font color=red>Error: Output not found:'.$fn.'()</font>');
		} //else  disp('<font color=red>Error: Output not found:'.substr($cmd, 15).'()</font>');
	}	
	done:
	$the_output .= '</td></tr></table>';
 }

set_data( $sql, ...$params ) ==> {	
	$out = get_data( $sql, ...$params );
	if ($out && $out.err) $GLOBALS['error_messages'] .= $out.err . '<br>';
	if ( $out.err ) {
		if ( appSettings->debug->sql ) {
			disp( fused( templates('error')->html, err:$out.err ) );
			disp( '<pre>',substr_replace( $out.sql,'<tt style="color:red;">', $out->offset ?? 0, 0).'</tt>', '</pre>' );
		}		
	}
	return $out;
 }

enum { //get_data flags
	LOCAL,
	SILENT,
	verbal,
	asArray,
}

directive ==> { hidecoltotals:1, report:2, refresh:4 };

get_data( ...$params ) ==> {	// [flags,] sql, params
	$flags = ( isset( $params[0] ) && is_int( $params[0] ) ) ? array_shift( $params ) : 0;

	if ( count( $params ) == 1 ) {		
		$k1 = array_key_first( $params );
		if ( valtype( $params[$k1] ) == 'record' && $k1 != 0 ) $params = [ $k1, ... (array) $params[$k1] ];											
	}
	
	$SQL = array_shift( $params );

	if ( valtype( $SQL )  == 'grecordset' ) return $SQL;

	$sql = $SQL;
	if ( valtype( $sql ) == 'record' ) {
		if ( isset( $sql.sql ) ) {
			$sql = $sql.sql;
		} else {			
			$k1 = array_key_first( (array) $sql );						
			$params = (array) $sql->$k1 + $params;			
			$sql = $k1;			
		}
	}
	if ( is_string( $sql ) ) $sql = SQL($sql) ?? $sql;
	if ( valtype( $sql ) == 'record' && isset( $sql.sql ) ) $sql = $sql.sql;
	if ( !$sql ) { disp( 'daphnia.' . __line__ . ': SQL not found:', $SQL ); die; }
	
	//capture directives 
		$directives = [];
		foreach ( directive as $d => $n ) {
			if ( ( $i = strpos( $sql, '/*' . $d ) ) !== false ) {
				$j = strpos( $sql, '*/', $i );  /*pos 1*/
				$directives[$d] = trim( substr( $sql, $i+strlen($d)+1, $j-1 ) ) ?: true;
			}
		}		

	$sql = fused( preserve_optionals + use_quotes, $sql, ...$params );

	if ( $sql[0] == '@' || str_contains( $sql, ';;' ) ) {
		$out = get_data_on_stack( $sql );
		if ( $out.row_count && !($flags & LOCAL) ) forge( $var, $out->data[0] );
		return $out;
	}

	$sql = dap_sql( $sql );

	if ( !$sql ) return {err: 'daphnia.' . __line__ . ': SQL not found:'. str($SQL) };
	
	//drop optionals
	$i = 0;
	if ( str_contains( $sql, '[' ) ) {
		while ( $j = strpos( $sql, ']', $i ) ) {
			if ( $sql[$j-1] == '`' ) { $sql = substr_replace( $sql, chr(3), $j-1, 2); goto next_opt; }			
			$i = strposrev( $sql, '[', $j );
			if ( $sql[$i-1] == '`' ) { $sql = substr_replace( $sql, chr(4), $i-1, 2); goto next_opt; }			
			$s = substring( $sql, $i+1, $j-1 );
			if (str_contains( $s,  '{$' ) ) $s = '';
			$sql = substring_replace( $sql, $s, $i, $j );
			next_opt:
		}
		$sql = str_replace( [chr(3), chr(4) ], [']', '['], $sql );		
	}
	$sql = str_replace( '`', '', $sql );

	$undefined = scanrx( $sql, '/\{\$(\w*)/' );	
	if ( $undefined ) {		
		return { 
			id: $SQL.id ?? 'sql',
			sql: $sql, 
			err:'Undefined input:' . join( ',', array_keys( $undefined[0] ) ), 
			row_count:0 };
	} 
	else {		
		if ( $flags & verbal ) disp( $sql );
		/*----------------------------*/
		$out = $oracle->opendata( $sql );
		/*----------------------------*/
	}
	$out.id = $SQL.id ?? 'output';
	$out.directives = $directives;

	if ( $out.row_count && ( $out.data[0]['_error_message'] ?? false ) ) { //soft error
		$out.row_count = 0;
		$out.err = $out.data[0]['_error_message'];
		return $out;
	}

	if ( $out.err && !( $flags & SILENT ) ) { //hard error
		$r = rand();
		disp( '<font color=red>ERROR - an internal error occurred - please report this to ICT - refer to log id #' . $r . '</font>' );
		file_put_contents( './daphnia.log', '#' . $r . "\n". str($GLOBALS['me']) . "\n\n" . str($out) . "\n----------", FILE_APPEND );
		if ( appSettings->debug->sql ?? false ) {
			disp( fused( templates('error')->html, err:$out.err ) );
			disp( '<pre>',substr_replace( $sql,'<tt style="color:red;">', $out->offset, 0).'</tt>' );
		}		
	}
	elseif ( $out.row_count && !($flags & LOCAL) ) {
		if ( $out->row_count > 1 && ( $flags & asArray ) ) {
			foreach ( array_keys( $out->data[0] ) as $k ) {	
				$var->$k = array_column( $out->data, $k );
			}				
		} else
			forge( $var, $out->data[0] );

		if ( !$out.err && appSettings->debug->sql ?? false ) disp( '<pre>execute:<br>',$sql,'</pre>' );
		if ( !$out.err && appSettings->debug->data ?? false ) disp( '<pre>data:<br>',$out,'</pre>' );		
		
	} else {
		if ( !$out.err && appSettings->debug->sql ?? false ) disp( '<pre>execute:<br>',$sql,'</pre>' );
		if ( !$out.err && appSettings->debug->data ?? false ) disp( '<pre>data:<br>',$out,'</pre>' );		
	}
	
	return $out;
 }

get_field_value( $name, $row = '' ) ==> {
	if ( ! isset( $_SESSION[ $_instance ][ 'input_fields' ][$name] ) ) return null;
	$prop = $_SESSION[ $_instance ][ 'input_fields' ][$name]; 
	$v = $_POST[ $name ] ?? $_POST[ $name . '_' . $row ] ?? '';	
	if ( is_array( $v ) ) {
		foreach( $v as $i => $vv ) {
			$v[$i] = preg_replace( '[^' . $prop['allow'] . ']', '', $vv );
			$v[$i] = preg_match( '/^' . $prop['filter'] . '$/', $vv ) ? $vv : '';
		}
	} else {
		$v = preg_replace( '[^' . $prop['allow'] . ']', '', $v);
		$v = preg_match( '/^' . $prop['filter'] . '$/', $v ) ? $v : '';
	}	
	return $v;	
 }

get_user_input( $table = '', $row = '' ) ==> {
	if ( ! isset( $_instance ) ) return;
	if ( ! isset( $_SESSION[ $_instance ] ) ) return;
	$r = $row ? '_' . $row : '';
	$var.prompt = preg_replace( '/[^A-Za-z0-9, ?.-]/', '', $_POST['_commandPrompt'] );
	foreach( $_SESSION[ $_instance ][ 'input_fields' ] ?? [] as $name => $prop ) {
		if ( isset( $_POST[ $name.$r ] ) ) {
			$v = $_POST[ $name.$r ] ?? '';
			$prop['allow'] ??= 'A-Za-z0-9';
			if (!$prop['allow']) $prop['allow'] = 'A-Za-z0-9';

			if ( is_array( $v ) ) {
				foreach( $v as $i => $vv ) {
					$v[$i] = preg_replace( '/[^' . $prop['allow'] . ']/', '', $vv );
					$v[$i] = preg_match( '/^' . $prop['filter'] . '$/', $vv ) ? $vv : '';
				}
				if ( count( $v ) == 1 ) $v = array_values($v)[0];
			} else {
				if ( $prop['allow'] != '.' ) $v = preg_replace( '/[^' . $prop['allow'] . ']/', '', $v);
				if ( $prop['filter'] != '.*' ) $v = preg_match( '/^' . $prop['filter'] . '$/', $v ) ? $v : '';
			}
			if ( $row && $prop['table'] == $table ) $var->$name = $v; 
			elseif ( $prop['table'] == 'input' ) $var->$name = $v;
			elseif ( $prop['table'] == 'output' ) $var->$name = $v; //input defined in output section
		}		
		if ( isset( $_FILES[$name] ) ) {
			if ( $_FILES[$name]['size'] && $_FILES[$name]['error'] === UPLOAD_ERR_OK ) {
				$var->$name = $_FILES[$name]['name'];
				$t = $name . '_contents';
				$var->$t = file_get_contents( $_FILES[$name]['tmp_name'] );
			}
		}	
	}
	foreach( $_SESSION[$_instance][ 'readonly_fields' ][$row] ?? [] as [$k,$v]) $var->$k = $v;

	if ( ! $row ) {
		$var->_ichanged = ($_POST['_this_ichanged']  ?? false ) ? split( '+', $_POST['_this_ichanged'] ) : [];
		$var->_ochanged = ($_POST['_this_ochanged'] ?? false ) ? split( '+', $_POST['_this_ochanged'] ) : [];			
		foreach( $var->_tables as $t => $rows ) {
			$t = str_replace( '.', '_', $t );
			$d = $_POST['_rows_changed_' . $t ] ?? '';
			$var->_rows_changed[$t] = $d ? split( '+', $d ) : [];	
		}
	}
	if ( appSettings->debug->vars ?? false ) disp( $var );
 }

// ['sql'] sql: table:{} name:'' rows:[] labels:[] flags:0	
// sqlname: { table: name: rows: labels: flags: }
//		table:{ label: data_field: format: }
// [ sql1, sql2 ]
do_output( ...$A ) ==> {

	if ( isset( $_POST['_ajax'] ) ) die('err:::ERROR: you cannot use do_output() in an action event:::');	

	//do multiple outputs into columns - do_output( [ O1, O2 ] )
	if ( count($A) == 1 && isset( $A[0] ) && valtype( $A[0] ) == 'list' ) {
		$the_output .= '<table><tr>';
		foreach ( $A[0] as $a ) {
			$the_output .= '<td valign=top>';
			do_output($a);
			$the_output .= '</td>';
		}
		$the_output .= '</tr></table>';
		return;
	}	

	//named parameter is a sql name:  do_output( sqlname: {} )
	$k1 = array_keys( $A )[0];
	if ( count( $A ) == 1 and valtype( $A[$k1] ) == 'record' ) {
		$sql = $k1;
		$params = (array)$A[$k1];
	}
	elseif ( isset( $A['sql'] ) ) { $sql = aa_pop( $A, 'sql'); $params = $A; }// do_output( sql: 'sql', ... )
	else { $sql = $A[0]; $params = array_slice( $A, 1 ); } // do_output( 'sql', ... )
	$params ??= aa_pop($A, 'params') ?? $A;


	$name ??= aa_pop($A, 'name');
	
	$out = get_data( $sql, ...$params );
	if ( $out.err ) {
		$the_output .= '<font color=red><br>' . $out.err . '</font><p>';
		return false;
	}

	$var->_row = 1;
	$var->_last_row = 0;
	
	$params ??= $A;
	$params = (object) $params;
	$params.table ??= null;
	$params.labels ??= null;
	$params.flags ??= 0;
	$params.rows ??= [];

	$name ??= $params.name ?? $out.id ?? 'output';
	if ( isset( $output_tables[ $name ] ) ) {
		$n = 1;
		while ( isset( $output_tables[ $name . '_' . $n ] ) ) $n++;	
		$name .= '_' . $n;
	}
	
	$output_tables[ $name ] = $out.row_count ?? 0;
	if ( $name[0] != '_' ) $the_output .= '<small><i class=tname>' . $name . '</i></small>';
	$the_output .= '<input type="' . $show_state . '" name="_rows_changed_' . $name . 
		'" id="_rows_changed_' . $name . '" placeholder="rows_changed..." value="">';
	$var->_table_name = $name;

	if ( isset( $params.table ) && is_callable( $params.table ) ) $params.table = ($params.table)();	

	if ( $out.err ) {
		$the_output .= '<font color=red><br>' . $out.err . '</font><p>';
	}
	elseif ( $out.row_count == 0 ) {
		$the_output .= '<br> no data <br> ';
	} 
	elseif ( ! isset( $params.table ) ) {
		$the_output .=  '<div class=output_panel>' . 
			$out->asTable( labels: $params.labels, flags: $params.flags ) . '</div>';
		$var->_last_row = $out->row_count;
	} 	
	elseif ( $out.row_count == 1 && ! ( $params.flags & table_wide ) ) { //single row
		$T = [];
		foreach( $params.header ?? [] as $f ) $T[] = $f;
		foreach( $out->data[0] as $k => $v )  $var->$k = $v;	//make this record visible		

		foreach( $params.table as $f => $def ) {
			if ( !isset( $def.format ) ) $def.format = { type:'readonly' };
			$def->format->data_field = $def.data_field ?? $f;
			if ( ( $def->format->type ?? '' ) == 'hidden') {
				$the_output .= input( $f, ...(array)$def.format ); 
			} else {
				$t = [];
				$def.label ??= $f;
				if ( $def.label && $def.label[-1] == '*' ) $def.label = '<span class=mandatory>' . $def.label . '</span>';
				$t[] = $def.label ?? $f;
				$t[] = input( $f,  ...(array)$def.format ); 							 		
				$T[] = $t;
			}
		}				
		$tbl = '<div class=output_panel>' . table( body:$T ) . '</div>';

		if ( count($params.rows) > 0 ) { //split table into cols			
			$c = 0;
			$r = 0;
			$i = 0;
			while ( $params.rows[$c] ?? false ) {
				$i = strpos( $tbl, '<tr', $i + 1 ); 
				if ($i === false) break;
				$r++;
				if ($r > $params.rows[$c] ) {
					$tbl = substr_replace( $tbl, '</table></td><td style="vertical-align:top; border-left:2px solid black;"><table>', $i, 0 );
					$r = 0;
					$c++;
				}
			}
			$tbl = substr_replace( $tbl, '<table><tr><td style="vertical-align:top;">', strpos( $tbl, '<table' ), 0); 
			$tbl = substr_replace( $tbl, '</td></tr></table>', strrpos( $tbl, '</div' ), 0); 			
		}
		$footer = '';
		foreach( $params.footer ?? [] as $f ) {
			if ( valtype( $f ) == 'closure' ) $f = $f();			
			elseif ( valtype( $f ) == 'record' ) $f = button( $f );		
			$tname = $var->_table_name;
			$f = preg_replace( '/tablename="[^"]*"/', "tablename=\"{$tname}\"", $f );
			$footer .= '<tr><td colspan=99>' . $f . '</td></tr>';
		}
		if ($footer) $tbl = substr_replace( $tbl, $footer, strrpos( $tbl, '</table' ), 0); 

		$the_output .= $tbl;

		$var->_last_row = 1;
	} 
	else { //multiple rows
		$T = [];
		foreach( $params.header ?? [] as $f ) $T[] = $f;
		//table column headers
		$t = ['<span style="color:rgb(180,180,180)">&nbsp;</span>'];
		foreach( $params.table as $f => $def ) {
			if ( ( $def->format->type ?? '' ) != 'hidden') $t[] = ($def.label ?? $f);			
		}
		$T[] = $t;
		//table column values
		foreach( $out->data as $record ) {				
			foreach( $record as $k => $v )  $var->$k = $v;	//make this record active	

			$r = $var->_row;
			$tname = $var->_table_name;
			$t = [<<<html
			<span id="_row_picked_{$tname}_{$r}" 
				class=row_picker tablename={$tname} row={$r}				
				onclick="toggle_row_picker('$tname', {$r} );">
				&nbsp;&num;{$var->_row}
			</span>
			html];
		
			foreach( $params.table as $f => $def ) {
				if ( !isset( $def.format ) ) $def.format = { type:'readonly'};
				$def->format->data_field = $def.data_field ?? $f;				
				if ( ( $def->format->type ?? '' ) == 'hidden') 
					$the_output .= input( $f, ...(array)$def.format ); 				 			
				else 
					$t[] = input( $f, ...(array)$def.format ); 
			}					
			$T[] = $t;
			$var._row++;
		}
		foreach( $params.footer ?? [] as $f ) {
			if ( valtype( $f ) == 'closure' ) $f = $f();			
			elseif ( valtype( $f ) == 'record' ) $f = button( $f );		
			$tname = $var->_table_name;
			$f = preg_replace( '/tablename="[^"]*"/', "tablename=\"{$tname}\"", $f );
			$T[] = [ $f ];
		}
		$the_output .= '<div class=output_panel>' . table( body:$T ) . '</div><br>';			
		$var->_last_row = $var->_row;
	}
	unset( $var->_row );

	$cmd = null;
	foreach( $_POST as $k => $v) 
		if (str_starts_with($k, 'command_' ) ?? false) { $cmd = substr( $k, 8); break; }	
	if ( $cmd ) $var->_state = instance( 'command_state' )[$cmd] ?? '';
	return true;
 }

/**
	do_action( 'select...' )
	do_action( 'sqlname' )
	do_action( sql: table: params: {} )
	do_action( insert:{ import: '1 as one', export:' {$out} as myout' , params: {one:1} } )
**/
// ['sql'] ['sqlname'] table: params:{} input/import:'' output/export:''
// sql: table: params:{} input/import:'' output/export:''
// sqlname: { table: params:{} input/import: output/export: }
// sql: { name: table: params:{} input//import: output/export: }
// sqlname: { param:value, param:value, table: input//import: output/export: }
do_action( ...$A ) ==> { // 'sql', table: params: sql:
	if ( ! isset( $_POST['_ajax'] ) ) die('ERROR: you cannot use do_action() in an output event');		

	$sql = $table = $imp = $exp = null;
	$params = [];

	//unpack the input
	if ( isset( $A[0] ) && valtype( $A[0] ) == 'closure' ) $sql = array_shift( $A );	
	elseif ( isset( $A[0] ) && valtype( $A[0] ) == 'string' ) $sql = array_shift( $A );	
	elseif ( isset( $A['sql'] ) && valtype( $A['sql'] ) == 'closure' ) $sql = aa_pop( $A, 'sql' );	
	elseif ( isset( $A['sql'] ) && valtype( $A['sql'] ) == 'string' ) $sql = aa_pop( $A, 'sql' );
	elseif ( isset( $A['sql'] ) && valtype( $A['sql'] ) == 'record' ) {
		$A = (array) $A['sql'];
		$sql = $A['name'] ?? $A[0] ?? null;		
		unset( $A['name'] );
	}
	elseif ( count( $A ) == 1 && valtype( array_values( $A )[0] ) == 'record' ) {
		$sql = array_keys( $A )[0];
		$A = (array) $A[ $sql ];
	}
	$table = aa_pop( $A, 'table' );
	$log = aa_pop( $A, 'log' );
	$imp = aa_pop( $A, 'input' ) ?? aa_pop( $A, 'import' );
	$exp = aa_pop( $A, 'output') ?? aa_pop( $A, 'export' ); 	

	$params = aa_pop( $A, 'params' ) ?? $A;	

	disp( 'do_action():', $sql, "($imp)($exp)" );

	$table ??= $_POST['_table_name'] ?? array_keys( $var._tables )[0] ?? null;
	if ( !$table ) return false;

	if ( is_array( $params ) ) $params = (object) $params;

	$out = { err:null };
	if ( $table == 'input' ) {
		$P = [];		
		foreach( $params ?? [] as $k => $v ) 
			if ( valtype( $v ) == 'closure' ) $P[$k] = $v();
			else $P[$k] = $v; //....???? $var->$v ?? '';

		if ( valtype( $sql ) == 'closure' ) $sql = $sql();
		if ( $imp || $exp ) $sql .= "($imp)($exp)";

		$out = set_data( $sql, ...$P );		
		if ( isset( $out->data[0]['error_message'] ) ) $out.err = $out->data[0]['error_message'];	
		if ( !$out.err ) add_log( $log );
		
	} else { 
		foreach( $var->_rows_changed[ $table ] as $row ) {
			get_user_input( $table, $row ); 	 //make row data active		
			$P = [];		
			foreach( $params ?? [] as $k => $v ) 
				if ( valtype( $v ) == 'closure' ) $P[$k] = $v();
				else $P[$k] = $v; // ....????  $var->{$v.'_'.$row} ?? $var->$v ?? '';	

			if ( valtype( $sql ) == 'closure' ) if ( ( $sql = $sql() ) === null ) return false;
			if ( $imp || $exp ) $sql .= "($imp)($exp)";

			$out = set_data( $sql, ...$P );		
			if ( isset( $out->data[0]['error_message'] ) ) $out.err = $out->data[0]['error_message'];		
			if ( $out.err ) break;
			add_log( $log );
		}
	}		

	if ( $out.row_count ?? 0 > 0 ) {
		$x = "set::: \n";
		foreach ( $out->data[0] as $k => $v ) {			
			$x .= "_id('" . $k . "').value = '" . ($v ?? '') . "';\n";
			$var->$k = $v;
			$x .= ':::<p>';	
		}
		echo $x;
	}

	$cmd = null;
	foreach( $_POST as $k => $v) 
		if (str_starts_with($k, 'command_' ) ?? false) { $cmd = substr( $k, 8); break; }	
	if ( $cmd && ($state = ( instance( 'command_state' )[$cmd] ?? false) ) ) 
		echo 'state:::' . $state . ':::';	

	return $out;
 }

do_validate( callable $fn, $table=null ) ==> {
	$table ??= $_POST['_table_name'] ?? array_keys( $var._tables )[0] ?? null;
	if ( !$table ) return false;	
	foreach( $var->_rows_changed[ $table ] as $row ) {
		get_user_input( $table, $row ); 
		$out = $fn();
		if ( !($out === true) ) return $row;
	}		
	return true;
 }

do_fail_on( ...$p ) ==> { //$table, $fn  

	$table = $p[0] ?? $p['table'] ?? null;
	$fn = $p[1] ?? $p['fn'] ?? null;
	if ( is_callable( $table ) ) [$table,$fn] = [$fn,$table];

	$table ??= $_POST['_table_name'] ?? array_keys( $var._tables )[0] ?? null;
	if ( $table ) foreach( $var->_rows_changed[ $table ] ?? [] as $row ) {
		get_user_input( $table, $row ); 
		if ( $err = $fn($table, $row) ) return $err;				
	}
	return false;
 }

rows_in_output( $t ) ==> {
	//can be used in actions
	$fields = array_filter_keys( $var->_fieldnames, ( $k, $v ) ==> $v == $t );
	$R = [];
	for $row = 1 .. $var->_tables[$t] {
		$t = {};
		foreach( $fields as $field => $x ) $t->$field = $_POST[ $field . '_' . $row] ?? '';
		$R[] = $t;
	}	
	return $R;		
 }

email_service ==> 'http://163.200.81.131/shared/emailme.aspx';
email( $from, $to, $subject, $message ) ==> {
	$email_data = "from={$from}&to={$to}&subject={$subject}&message={$message}";
	$hash = hash256( $email_data );
	$postdata = http_build_query( [
        'data' => base64_encode( $email_data ),
        'hash' => base64_encode( $hash ),
    ] );
	$opts = [ 'http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $postdata
    ]];
	$context  = stream_context_create( $opts );
	return file_get_contents( email_service, false, $context );
 }

write( ...$s )    ==> { foreach ( $s as $ss ) $the_output .= str( $ss ) . ' ';  }
writeln( ...$s )  ==> { write( ...array_map( ($a) ==> str($a).'<br>', $s ) ); }
error($s)		  ==> { $the_output .= '<font color=red>Error:' . $s . '</font><br>'; return false; }
evaluate($x) ==> $x;

get_stored_sql( $s ) ==> { // @1234('2026' as xyear)

	$s = (string) $s;
	if ( $s[0] == '@' || $s[0] == '#' ) $s = substr( $s, 1 ); 	
	$imp = $exp = '';
	if ( ( $i = strpos( $s, '(' ) ) !== false ) {
		$j = str_paired( $s, $i );
		$imp = trim( substring( $s, $i + 1, $j - 1) );
		if ($imp) $imp = 'select ' . $imp . ';;';
		$s = substring_replace( $s, '', $i, $j);
	}
	if ( ( $i = strpos( $s, '(' ) ) !== false ) {
		$j = str_paired( $s, $i );
		$exp = trim( substring( $s, $i + 1, $j - 1) );
		if ( $exp ) $exp = ';; select ' . $exp;
		$s = substring_replace( $s, '', $i, $j);
	}

	$s = trim($s);
	$sql = SQL( $s );
	if ( !$sql ) {
		$sqlseq = get_data( "select sql0 from sqlseq where sql_id = $s order by sequence_nr" );
		if ( $sqlseq->err ) return $sqlseq;	
		foreach ( $sqlseq->data as $p ) $sql .= $p['sql0'] . ' ';
		$sql = preg_replace_callback( '/([^{])(\\$\w+)/', 
			($x) ==> strtolower( $x[1] . '{'.$x[2].'}'), $sql );
		$sql = $imp . $sql . $exp;	
	} else {
		$sql = $imp . $sql.sql . $exp;	
	}
	return $sql;

	/*
	$id = $s; 
	$sql = '';

	//pop imports
	if ( find( $s, '(', 0, $i ) ) {
		$id = left( $s, 0, $i);
		$sql = 'select ' . substring( $s, $i + 1, -2) . ' from dual ;;';			
	}
	$sqlseq = get_data( "select sql0 from sqlseq where sql_id = $id order by sequence_nr" );
	if ( $sqlseq->err ) return $sqlseq;	
	foreach ( $sqlseq->data as $p ) $sql .= $p['sql0'] . ' ';
	$sql = preg_replace_callback( '/([^{])(\\$\w+)/', 
		($x) ==> strtolower( $x[1] . '{'.$x[2].'}'), $sql );
	//pop exports....
	return $sql;
	*/
 }

get_data_on_stack( $stack, $params=null ) ==> {
	$stack = trim( $stack );
	if ( ! $stack ) return null;	

	if ($params) $stack = fused( preserve_optionals + use_quotes + no_globals + no_defaults, $stack, $params );

	while ( $stack ) {

		//disp('stack:', $stack );		

		//pop top sql		
			$n = 0; $q = null; $f1 = null; $f9 = null;
			for $i = 0 .. strlen( $stack ) - 1 {
				case $stack[$i] {
					'"', "'" : if ( $q == $stack[$i] )  $q = null; elseif ( $q === null ) $q = $stack[$i];
					'{': if ( !$q ) { $n++; if ( $n == 1 && ($stack[$i+1] ?? '$') != '$' ) $f1 ??= $i; };
					'}': if ( !$q ) { $n--; if ( $f1 && $n == 0 ) $f9 ??= $i; };
					';': if ( !$q && $n == 0 && ( $stack[$i+1] ?? ';' ) == ';' ) goto found_seperator;
				}
			} 
			found_seperator:
			$sql = trim( substr( $stack, 0, $i ) ); $stack = trim( substr( $stack, $i+2) );
		
		// {grouped}
			if ( $f1 == 1 && $f9 ) {
				$RS = get_data_on_stack( substr( $sql, $f1 + 1, $f9 - 1 ) );
				if ( $RS->err ) return $RS;
				goto bind_values;
			}

		// foreach {} else
			if ( $f1 && $f9 ) {
				$RS = get_data_on_stack( substr( $sql, 0, $f1 ) );	if ( $RS->err ) return $RS;
				if ( $RS.row_count ) {
					$sql = trim( substring( $sql, $f1 + 1, $f9 - 1 ) );
					if ( !$sql ) goto bind_values;
					foreach( $RS->data as $params ) {
						$tRS = get_data_on_stack(
							fused( preserve_optionals + use_quotes + no_globals + no_defaults, $sql, $params ) 
						);	
						if ( $tRS->err ) return $tRS;
					}				
				} else {
					$RS = get_data_on_stack( substr( $sql, $f9 + 1 ) ); if ($RS->err) return $RS;
				}
				goto bind_values;
			}

		// @call()
			if ( $sql[0] == '@' ) {  // @123( 1 as one )
				$sql = get_stored_sql( $sql );					
				if ( ! is_string( $sql ) ) return $sql;
				$RS = get_data_on_stack( $sql ); if ( $RS->err ) return $RS;
				goto bind_values;
			}
			
		$RS = get_data( LOCAL, $sql );			 
		if ($RS->err) return $RS;
		if ( isset( $RS->data[0]['_error_message'] ) ) { 
			$RS.row_count = 0; 
			$RS->err = $RS->data[0]['_error_message']; 
			return $RS; 
		}

		if ( str_contains( $sql, '/*REPORT*/' ) ) write( $RS->asTable() );

		bind_values:
		if ( $RS.row_count ) {
			$stack = fused( preserve_optionals + use_quotes + no_globals + no_defaults, $stack, $RS->data[0] );
		}
	}

	return $RS;
 }

export_values( ...$V ) ==> { // varname, varname: 'val', var: $var.varname
	if ( isset( $_POST['_ajax'] ) ) {
		$x = 'let e;';
		foreach ( $V as $n => $v ) {
			if ( is_int( $n ) )	$n = $v;
			$v = $var->$v ?? $v;
			$x .= <<<javascript
				e = _id('{$n}');
				if ( e.getAttribute( 'value0' ) == e.value ) e.setAttribute( 'value0', '{$v}' );
				try{ e.value = '{$v}'; } catch(ignored){};
				try{ e.innerHTML = '{$v}'; } catch(ignored){};
			javascript;
		}
		echo 'set:::' . $x . ':::<p>';
	} else {
		foreach ( $V as $n => $v ) {
			if ( is_int( $n ) )	$n = $v;
			$_POST[$n] = $var->$v ?? $v;
		}
	}
 }
export_error( string $e ) ==> {
	if ( isset( $_POST['_ajax'] ) ) echo "err:::".$e.":::\n";		
	else $_POST['_this_error'] = $e;		
 }
export_state( string $e ) ==> {
	if ( isset( $_POST['_ajax'] ) ) echo "state:::".$e.":::\n";		
	else $_POST['_this_state'] = $e;		
 }

for_all_rows( $table='output' ) ==> $var->_rows_changed[$table] = range( 1, $var._tables[$table] );

Daphnia() ==> {
	global $me, $out;	
		
	published $show_state = (appSettings->debug->state ?? false)  ? 'text' : 'hidden';  
	
	foreach( $_SESSION as $i => $s ) {
		if ( isset( $s['_timeout'] ) ) {
			if ( $s['_timeout'] < time() ) unset( $_SESSION[$i] );
			else $_SESSION[ $i ][ '_timeout' ] = time() + $app_timeout;		
		}
	}	
	
	if ( isset( $_SESSION[ 'args' ] ) ) {
		foreach( $_SESSION[ 'args' ] as $id => $args) {
			$args = unserialize( $args );
			if ( $args['to'] < time() ) unset( $_SESSION[ 'args' ][ $id ] );
			else {
				$args['to'] = time() + $app_timeout;
				$_SESSION[ 'args' ][ $id ] = serialize($args);
			}
		}
	}	

	$var = instance( 'var' ) ?? obj();

	$args = false;
	$id = $_REQUEST['args'] ?? false;
	if ( $id && isset( $_SESSION[ 'args' ][ $id ] ) ) {
		$args = unserialize( $_SESSION[ 'args' ][ $id ] );
		$params = $args['params'];	
		$var = forge( $var, $args['vars'] );
		$var = forge( $var, $params );
		foreach ($params as $k => $v ) $locked[ $k ] = $v;
		unset( $var._row );
		//instance( 'var' ) = $var;		
	}

	if ( $var.report ?? null ) {			
		write('
			<style> 
				table {font-size:11px;}
				td {padding: 0 20px 0 0; } 
				tr:nth-child(odd) { background-color: rgb(230,230,200); }
			</style>
			');
		$report = $var.report;			
		$report();			
		disp( $the_output );			
		$oracle->rollback();
		session_write_close(); 
		die;			
	}
	
	$var->_fieldnames = isset( $_POST['_this_fieldnames'] ) ? unserialize( base64_decode( $_POST['_this_fieldnames'] ?? '' ) ) : [];
	$var->_tables = isset( $_POST['_this_rows'] ) ? json_decode( base64_decode( $_POST['_this_rows'] ), true ) : [];	

	get_user_input();
	
	echo( '<script language=javascript> var debugAppSettings = "' . 
		substr( str( array_keys( array_filter( (array)appSettings->debug, ($i) ==> $i > 0 ))), 6, -1) . 
		'"; </script>' );

	if ( isset( $_POST['_ajax'] ) ) { 
		
		perform_actions_events;					
		
		if ( $out.err || appSettings->debug->rollback ?? false ) $oracle->rollback(false);
		else $oracle->commit(); 	

		if ( isset( $out->notes ) && $out->notes ) disp( 'notes:::' . $out.notes . ':::<p>' );

		instance( 'var' ) = $var;
		session_write_close();
		die;		 
	}

	perform_output_events;

	disp( 
		fused( templates('form_open')->html, appTitle, this_instance: $_instance, 
		uid: $me.username,
		time: date('Y-d-m H:i', time() ),
		db: $oracle.db,
		menu: defined('appMenu') ? menu(appMenu) : null,			
		 ) );

	$var->_table_name = 'input';

	disp( '<div class=input_panel>' . table( appInput ) . '</div>' );	
	
	disp( $the_output );	

	disp( fused( templates( 'form_close' )->html, 
		error_messages: $GLOBALS['error_messages'],
		rows: base64_encode( json_encode( $output_tables ) ),
		fieldnames: base64_encode ( serialize( $used_fieldnames ?? '' ) ), 
	) );

	if ( ($out.err ?? false) || appSettings->debug->rollback ?? false ) $oracle->rollback(false);
	else $oracle->commit(); 	 

	instance( 'var' ) = $var;
	session_write_close();
	die;
}

//------------------- shared html templates -------------------------
	templates( $id ) ==> {		
		$templates ??= {

			form_open: { html: <<<'html'
				<head>
				<link rel="stylesheet" href="daphnia.css">
				<script src="daphnia.js"></script>				
				<title>SIS-F{$fnr}</title>
				</head>
				
				<table width=100% border=0><tr><td style="border-bottom:1px solid black">
					<table><tr><td>
					<tr ><td nowrap>
						<span style="font-size:20px;font-weight:900">UNISA - F{$fnr} {$title}</span><br>						
						<i><span style="background-color:silver; display:block; padding:15px;">{$notes=}</span>
					</td><td width=100% valign=bottom align=right>
						{$uid}<br>
						{$time}<br>
						{$db}
					</td></tr>
				</table>
				</td></tr></table>

				<form name=myform method=post enctype="multipart/form-data">
				{$menu=}<br>
				<input type=hidden name=_this_instance value="{$this_instance}" >					
				<input type={$show_state} name=_this_error id=_this_error value="" placeholder='error'>
				<input type={$show_state} name=_this_changed id=_this_changed value="" placeholder='changed'>
				<input type={$show_state} name=_this_ichanged id=_this_ichanged value="" placeholder='ichanged'>
				<input type={$show_state} name=_this_ochanged id=_this_ochanged value="" placeholder='ochanged'>
				<input type={$show_state} name=_this_kchanged id=_this_kchanged value="" placeholder='kchanged'>
				<input type=hidden name=_commandPrompt id=_commandPrompt value=''>
				html,
			 },			
			form_close: { html: <<<'html'
				<span id=error_message style="color:red">{$error_messages=}</span>
				<input type={$show_state} name=_this_rows  id=_this_rows  value="{$rows=}" placeholder='rows'>
				<input type={$show_state} name=_this_state id=_this_state value="{$_state=}" placeholder='state'>
				<input type=hidden text name=_this_fieldnames value="{$fieldnames}" >
				</form>
					<script language=javascript>
					input_changed();
					</script>
				<hr>
				html,
			 },
			label: { 
				tags: '',
				label: '',
				html: <<<'html'
				<label {$tags=} >{$label}</label>
				html,
			 },
			/*readonly_input: { html: <<<'html'
				<label {$style} >{$label=}{$value=}<input type=hidden
					name="{$name_r}" id="{$name_r}" value="{$value=}" {$tags=} > </label>
				html,
			 },	*/
			readonly_input: { html: <<<'html'
				<label {$style} >{$label=}<input inert
					type=text style='border:0px;field-sizing: content; background:none;'
					name="{$name_r}" id="{$name_r}" value="{$value=}" {$tags=} > </label>
				html,
			 },
			hidden_input: { html: <<<'html'
					<input type=hidden name="{$name_r}" id="{$name_r}" value="{$value=}" {$tags=} >
				html,
			 },	
			text_input: { html: <<<'html'
				<label style="{$style}" >{$label=}<input style="{$style}"  type={$type}					
					name="{$name_r}" id="{$name_r}" size={$size} maxlength={$max_size} 
					value="{$value=}" value0="{$value=}"				
					oninput="this.value = this.value.replace(/[^{$allow}]/g, ''); input_changed(this);"	
					onblur="validate_io();"
					pattern="{$filter}"
					oninvalid="stop_working(this);"
					title="{$hint=}"
					{$tags=}
				> {$units=} </label>
				html,				
			 },
			TEXT_input: { html: <<<'html'
				<label style="{$style}" >{$label=}<input style="{$style}"  type={$type}
					name="{$name_r}" id="{$name_r}" size={$size} maxlength={$max_size} 
					value="{$value=}" value0="{$value=}"				
					oninput="this.value = this.value.toUpperCase(); this.value = this.value.replace(/[^{$allow}]/g, ''); input_changed(this);"	
					onblur="validate_io();"
					pattern="{$filter}"
					oninvalid="stop_working(this);"
					title="{$hint=}"
					{$tags=}
				> </label>
				html,				
			 },
			textarea_input: { html: <<<'html'
				<label {$style} >{$label=} <textarea {$style}				
				name="{$name_r}" id="{$name_r}" rows={$rows=5} cols={$cols=60}
					 value0="{$value=}" 
					 oninput="input_changed(this);"		
					 pattern="{$filter=[a-zA-Z0-9\s\r\n\t;,.-]*}"
					 onblur="validate_io();"
					 oninvalid="stop_working(this);"
					 {$tags=}
				>{$value=}</textarea></label>
				html,
			 },
			number_input: { html: <<<'html'
				<label {$style=} >{$label=}<input {$style} style="text-align:right"
				name="{$name_r}" id="{$name_r}" size={$size} maxlength={$max_size}
					value="{$value=}" value0="{$value=}" inputmode="numeric" 
					 oninput="this.value = this.value.replace(/[^{$allow=0-9}]/g, ''); input_changed(this);"		
					 pattern="{$filter=[0-9]*}"
					 onblur="validate_io();"
					 minmax="{$minmax=}"
					 title="{$hint=}"
					 oninvalid="stop_working(this);"
					 {$tags=}
				> {$units=} </label>
				html,
			 },
			password_input:{ html: <<<'html'
				<label {$style} >{$label}<input type=password name="_{$name}" id="_{$name}" size={$size} maxlength={$size}				
					oninput="_id('{$name}').value = btoa( this.value ); input_changed(this);"
				></label>
				<input type=hidden name="{$name}" id="{$name}">
				html,		
			 },
			time_input: { html: <<<'html'
				<label {$style} >{$label}<input type=time name="{$name_r}" id="{$name_r}" 
					value="{$value}" value0="{$value}" 
					oninput="this.value = this.value.replace(/[^0-9\-\:\sT]/g, ''); input_changed(this);"	
					pattern="[0-9\:]*"
					onblur="validate_io();" {$tags=}
					></label>
				html,
			 },
			datetime_input: { html: <<<'html'
				<label {$style=} >{$label=}
				<input type=hidden name="{$name_r}" id="{$name_r}" 
					value="{$dtvalue=}" value0="{$dtvalue=}" {$tags=}>
				<input type="datetime-local" name="_{$name_r}" id="_{$name_r}" 
					value="{$value=}" value0="{$value=}"
					oninput="					
						this.value = this.value.replace( /[^0-9\-\:\sT]/g, ''); 
						const e = _id('{$name_r}');
						e.value = this.value.replace('T', ' '); 
						input_changed(this);"							
					{$tags=}
					></label>					
				html,
			 },			
			picker_input:{ html: <<<'html'
				<input type=hidden name={$name_r} id={$name_r} value="{$value=}" value0="{$value=}" {$tags=} >
				{$options}								
				html,
			 },
			picker_input_option:{ html: <<<'html'
				<label><input type=radio name="pick_{$name_r}" id="{$name_r}_picker_{$code}" value="{$code}" 
				{$checked}	onclick="radio_picker( this, '{$name_r}' );"  {$tags=} >{$label}</label>&nbsp;&nbsp;
				html,
			 },	
			yesno_input: { 
				allow: 'YN',
				filter: '[YN]',	
				html: <<<'html'
					<input type=hidden name={$name_r} id={$name_r} value="{$value}" value0="{$value}" 
					{$tags=} >
					<label forx=pick{$name_r} title="{$hint=}"> {$label=}
					<input type=checkbox id=pick{$name_r} value="Y/N" ivalue={$value=Y}						
						{$checked=}						
						onclick="
							let e = _id('{$name_r}');
							e.value = e.value == 'Y' ? 'N' : 'Y';
							this.checked = e.value == 'Y';
							input_changed(e);
						"  {$tags=}
						> </label>
				html,
			 },
			boxpicker_input:{ html: <<<'html'
				<input type=hidden name={$name_r} id={$name_r} value="{$value}" value0="{$value}" >
				<table border=0 style='display:inline;margin:0px;'><tr>
					{$options}
				</tr></table>				
				html,
			 },	
			boxpicker_input_option:{ html: <<<'html'
				<td id="{$name_r}_picker_{$code}" style='cursor:pointer; padding:0 5px 0 5px;' value={$code} 
				{$checked}	onclick="box_picker(this,'{$name_r}', {$multi} );"  {$tags=} >{$label}</td>
				html,
			 },
			list_input:{ html: <<<'html'
				<select name="{$name_r}[]" id="{$name_r}" {$multi} 
					oninput="input_changed(this);" value="{$value=}" value0="{$value=}" {$tags=} >
					<option value="">--select--</option>
					{$options}
				</select>				
				html,
			 },	
			list_input_option:{ html: <<<'html'
				<option value={$code} {$checked=} title="{$hint=}">{$label}</option>
				html,
			 },
			url_input: {
				allow:	'\w:\\/.?=&',
				type:	'url',	
				filter: 'http[s]?:\/\/.*',
				refer:	'text_input',
				html: <<<'html'
					<label style="{$style}" >{$label=}<input style="{$style}"  type={$type}
						
						name="{$name_r}" id="{$name_r}" size={$size} maxlength={$max_size} 
						value="{$value=}" value0="{$value=}"				
						oninput="this.value = this.value.replace(/[^{$allow}]/g, ''); input_changed(this);"	
						onblur="validate_io();"
						pattern="{$filter}"
						oninvalid="stop_working(this);"
						title="{$hint=}"
						{$tags=}
					> <a href='' onclick="window.open( _id('{$name_r}').value );return false;">go</a> </label>
					html,				
			 },
			button: {
				label:	'submit',
				html: <<<'html'
				<input type=submit class=button
					name="command_{$cmd}" 
					id="command_{$cmd}" value="{$label}" 
					title="{$hint=}"	requires="{$requires=}" 					
					onclick="{$onclick=}" {$tags=} 
					>&nbsp;&nbsp;
				html,
			 },
			menu: {				
				html: <<<'html'
				<input type=submit class=menu 
					name="command_{$cmd}" 
					id="command_{$cmd}" value="{$label}" 
					title="{$hint=}" requires="{$requires=}" 
					onclick="{$onclick=} return submitWith(this);" {$tags=} >&nbsp;&nbsp;
				html,
			 },
			alink: {
				html: <<<'html'
					<a href="{$url=}" class=alink
					name="command_{$cmd}" 
					id="command_{$cmd}"  
					title="{$hint=}" requires="{$requires=}" 
					target={$target=_blank}
					onclick="{$onclick=}" {$tags=} >{$label}</a>
				html,
			 },
			error: { html: <<<'html'
				<span style="color:red">{$err}</span>
				html,
			 },
			cell_input: {
				allow:	'0-9',				
				filter:	'[+]?\d{10,11}|0|\s*', 
				size:	11,				
				refer:	'text_input',	
			 },			
			email_input: {
				allow:	'a-zA-Z0-9@_\.\-',					
				filter:	'[\w\.\-]+@[\w\.\-]+',
				refer:	'text_input', 					
			 },
			date_input: { html: <<<'html'
				<label {$style} >{$label=}<input type="date" name="{$name_r}" id="{$name_r}" 
					value="{$value=}" value0="{$value=}" 
					oninput="this.value = this.value.replace(/[^0-9\-\:\sT]/g, ''); input_changed(this);"	
					pattern="[0-9\-\:\sT]*"
					onblur="validate_io();" {$tags=}
					></label>					
				html,
			 },	
			tel_input:	{
				allow:	'0-9',					
				filter: '[+]?\d{10,11}|0|\s*',			
				size:	12,
				refer: 'text_input',
			 },			
			search_input: {
				type:	'search',
				refer:	'text_input',
			 },
			range_input: {
				type: 'range',
				refer:	'text_input',
			 },
			file_input: {							
				type: 'file',	
				html: <<<'html'
					<label style="{$style}" >{$label=}<input style="width:75px;{$style}"  type={$type}					
					name="{$name_r}" id="{$name_r}" size={$size} maxlength={$size} 
					value="{$value=}" value0="{$value=}"
					oninput="
						if ( this.files[0].name ) {
							this.setAttribute('value0', this.files[0].name ); 
							_id('{$name_r}filename').innerHTML = this.files[0].name;
						}
						input_changed(this);"	
					title="{$hint=}"
					{$tags=}
				> </label><span title='clear' style="border:1px solid black; color:#F00;cursor:pointer;" 
					onclick="
						let e = _id('{$name_r}');
						e.value = '';
						e.setAttribute('value0', '' ); 
						_id('{$name_r}filename').innerHTML = '...';
						input_changed(e);
					"
					>X</span>
					<span id="{$name_r}filename">...</span>
				html,
			 },
		};

		try {
			$T = function_exists('appTemplates') ? (appTemplates->$id ?? null ) : null;
			$T ??= $templates->$id ?? '';	

			if ( is_object( $T ) ) $T = clone( $T );
			
			while ( $T.refer ?? false ) {
				$t = $T.refer; 
				unset( $T.refer );
				$T = forge( templates( $t ), $T );	
			}
			return $T;
		} catch (Throwable $x) { return null; } 

	}
//------------------- shared sql templates -------------------------

	SQL($id, ...$params) ==> { 
		$globalSQLs = {	
			
			XMO: {
				listColleges: 'select code, abbreviation from colleg order by 2',

				listModuleExamStatus: <<<'sql'
					select code, eng_description from gencod where gencod.fk_gencatcode = 37,
						nvl( IN_USE_FLAG,'Y') <> 'N' order by info
				sql,
			},

			listDepartments: <<<'sql'
				select dpt.code, colleg.abbreviation || '-' || initcap(dpt.eng_description) 
				from colleg,dpt where colleg.code = dpt.college_code and dpt.in_use_flag <> 'N' order by 2
				sql,			

			listGENCOD: <<<'sql'
				select code, eng_description as description from gencod where gencod.fk_gencatcode = 0[{$category}][{$code}]
					and nvl( IN_USE_FLAG,'Y') <> 'N'
				order by eng_description
				sql,

			gencod: <<<'sql'
				select gencod.eng_description as description from gencod where 
					gencod.fk_gencatcode = {$category},					
					gencod.code = {$code},
					nvl( gencod.IN_USE_FLAG,'Y') <> 'N'
				sql,
		};

		$SQL = null;
		if ( function_exists( 'appSQLs' ) ) {
			$SQL = appSQLs;
			foreach( split( '.', $id ) as $sid ) {
				if ( ! isset ( $SQL->$sid ) ) { $SQL = null; goto try_globals; }
				$SQL = $SQL->$sid;
			}			
		}
		try_globals:
		if ( ! $SQL ) {
			$SQL = $globalSQLs;
			foreach( split( '.', $id ) as $sid ) {
				if ( ! isset ( $SQL->$sid ) ) return null; 
				$SQL = $SQL->$sid;
			}
		}
		if ( ! $SQL ) return null; //disp( 'Error: SQL not found:' . $id ); die;	

		if ($params) {
			if ( gettype( $SQL ) == 'string' ) {
				$SQL = fused( use_quotes, $SQL, ...$params );
			}
			if ( isset( $SQL->sql ) ) {
				$SQL->sql = fused( use_quotes, $SQL->sql, ...$params );
			}
		}
		$out = gettype( $SQL ) == 'string' ? {sql:$SQL} : clone($SQL);
		$out.id = $id;

		return $out;
	}
//------------------- reference---------------------
/*
	
				input()		button()	label()		alink()		menu()
	name:		y!
	type:		y*
	size:		y
	filter:		y															[a-z]*		/^regexp$/		
	allow:		y															a-z			keypress filter							
	label:		y			y!			y!			y!			y
	opt:		y						y
	validate	y
	output:					y									y
	action:					y									y
	report:					y									y
	requires:				y**									y**
	hint:					y						y			y
	confirm:				y						y			y
	params:{}				y						y			y
	url:					y						y			y
	app:					y						y			y
	options		y
	multi		y
	sql			y
	tags		y			y						y			y
	minmax		y
	rows		y
	cols		y
	html		y									y			y
	value		y

	! can be an unnamed parameter, which would be positionally the first argument

	* input types: 
		text, par, cell, email, date, time, password, file,
		tel, url, search, number, range, color, 
		picker, boxpicker, list 

	** changed, ichanged, ochanged, kchanged, state, *_rows, error, <fieldname>, <value>
		/ or
		, and
		() precedence
		= > < >= <= equality
		== any+of is any+of

	appSettings.debug = state,sql,var
	*/

// data_state....
//	requires: valid(in_studno),...
//@sql('import')('export') ....make export additional field renames
//	select T.*, {$export} from ( ) T
// pagination....
// menus ....

?>