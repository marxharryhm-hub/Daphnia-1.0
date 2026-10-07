
function inflag( F, f ) { return ('+' + F + '+').indexOf('+' + f + '+') >= 0; }
function setflag( F, f ) { if (inflag(F,f)) return F; else return F ? F + '+' + f : f;}
function delflag( F, f ) { return ('+' + F + '+').replace('+' + f + '+', '+').substring(1,-1); }

function _id( id ) { return document.getElementById( id ); }

var Tablename = '';

function eval_req( r ) {
	//	field, field = value, (field / state=value)			
	let i,j;
	r = r.trim();
	if (r == '1') return true;
	if (r == '0') return false;
	if (r == '' ) return false;

	j = r.indexOf( ')' ); //()
	if (j >= 0) {
		i = r.lastIndexOf( '(', j ); 
		r = r.substr(r, i) + eval_req( r.substring( i+1, j ) ) + (j+1 < r.length ? r.substr(j+1) : '' );
		return eval_req( r );
	}
	i = r.indexOf( ';' ); //hi-OR
	if (i >= 0) return eval_req( r.substr(0,i) ) || eval_req( r.substr(i+1) );				
	
	i = r.indexOf( ',' ); //AND
	if (i >= 0) return eval_req( r.substr(0,i) ) && eval_req( r.substr(i+1) );

	i = r.indexOf( '/' ); //lo-OR
	if (i >= 0) return eval_req( r.substr(0,i) ) || eval_req( r.substr(i+1) );
	
	if ( r.charAt(0) == '-' ) return ! eval_req( r.substr(1) ); //NOT						
	if ( r.charAt(0) == '!' ) return ! eval_req( r.substr(1) ); //NOT

	// table_changed=output_1 //we do this here as multiple tables could have changed
		if (r.startsWith('table_change' )) { 
			i = r.indexOf( '=' );
			let t = i > 0 ? r.substr( i + 1 ) : Tablename;
			return _id('_rows_changed_' + t).value;
		}		

	// any of field/value == any of field+value...
	i = r.indexOf( '==' ); //any equal to any
	if (i >= 0) { 
		const R1 = r.substr(0,i);
		const R2 = r.substr(i+1);
		for ( const r1 of R1.split('+')) {
			const v1 = eval_req( r1 );
			for ( r2 of R2.split('+')) {
				const v2 = eval_req( r2 );
				if (v1 == v2) return true;
			}
		}					
		return false;
	}											
	i = r.indexOf( '<>' ); //not equal
	if (i >= 0) return eval_req( r.substr(0,i) ) != eval_req( r.substr(i+1) );
	i = r.indexOf( '>=' ); 
	if (i >= 0) return eval_req( r.substr(0,i) ) >= eval_req( r.substr(i+1) );
	i = r.indexOf( '<=' ); 
	if (i >= 0) return eval_req( r.substr(0,i) ) <= eval_req( r.substr(i+1) );

	i = r.indexOf( '>' ); 
	if (i >= 0) return eval_req( r.substr(0,i) ) > eval_req( r.substr(i+1) );
	i = r.indexOf( '<' ); 
	if (i >= 0) return eval_req( r.substr(0,i) ) < eval_req( r.substr(i+1) );
	i = r.indexOf( '=' ); //equal
	if (i >= 0) return eval_req( r.substr(0,i) ) == eval_req( r.substr(i+1) );

	let e = _id( r );
	if (!e) e = _id( r + '_1' );
	if ( e && ( e.type=='checkbox' || e.type=='radio' ) ) return e.checked;
	else if (e) return e.value;

	if ( r.at(-1) == '*' ) {		
		const E = document.querySelectorAll('[id^="' + r.slice(0,-1) + '"]');
		for ( const e of E ) {
			if (!e.value) return false;
		}
		if (E) return true;
	}	

	if (r == 'state') { e = _id( '_this_state' ); if (e) return e.value; }	
	
	if (r.startsWith('picked' )) { 
		let p = document.querySelector(`[id^=_row_picked_${Tablename}][class~=hand_picked]` );
		return p;		
	}
	
	//...valid(field)
	
	if (r.startsWith('state(' )) { e = _id( '_this_state'  ); if (e) return e.value; }
	
	if (r.startsWith('change' )) { e = _id( '_this_changed'  ); if (e) return e.value; }
	if (r.startsWith('ichange')) { e = _id( '_this_ichanged' ); if (e) return e.value; }
	if (r.startsWith('ochange')) { e = _id( '_this_ochanged' ); if (e) return e.value; }
	if (r.startsWith('kchange')) { e = _id( '_this_kchanged' ); if (e) return e.value; }
	if (r.startsWith('err'    )) { e = _id( '_this_error'    ); if (e) return e.value; }

	//per table, how many rows? open_clemency_1_rows
	if ( r.endsWith( '_rows' ) ) {						
		const T = JSON.parse( atob( _id( '_this_rows' ).value ) );
		let n = T[ r.substring( 0, r.length-5 ) ] || 0;
		return n;
	}

	return r;
 }

function enable_buttons() {						
	const buttons = document.querySelectorAll('input[requires]')
	for ( const button of buttons ) {
		const req = button.getAttribute('requires');	
		Tablename = button.getAttribute('tablename');	
		if ( !req || eval_req( req ) ) button.style.color = '#000';
		else button.style.color = '#aaa';						
	}
 }

function stop_working( t ) {
	const buttons = document.querySelectorAll('input[type=submit]')
	for (const button of buttons) {
		button.setAttribute('working', 0);					
		button.value = button.getAttribute('label') || button.value;
	}
	input_changed(this);
	_id('_this_error').value = setflag( _id('_this_error').value, t.name );
 }			 

function input_changed( t=null ){				
	let e  = _id('_this_changed' );
	let ic = _id('_this_ichanged');
	let oc = _id('_this_ochanged');
	let kc = _id('_this_kchanged');

	for ( let ie of document.querySelectorAll("[id^='_rows_changed_']") ) ie.value = '';
	for ( let ie of document.querySelectorAll('error') ) ie.innerHTML = '';

	if ( e && ic && oc && kc ) {
		e.value = '';
		ic.value = '';
		oc.value = '';
		kc.value = '';
		for ( const p of document.querySelectorAll('[id^="_row_picked_"]') ) {
			p.classList.remove('boxed');
		}
		
		const input = document.querySelectorAll('input[value0], select[value0]');
		for ( const i of input ) {
			i.classList.remove('changed');						
			let r = i.getAttribute('row');
			let tn = i.getAttribute('tablename');
			let p = _id('_row_picked_' + tn + '_' + r);			
			let v = i.value.replace(/^["']|["']$/g, '').trim();					//'
			let v0 = i.getAttribute('value0').replace(/^["']|["']$/g, '').trim();	//'
			if ( v != v0 ) {
				i.classList.add('changed');
				let iname = i.name.replace( /[\[\]]/g, '' );
				if ( iname.charAt(0) != '_' ) e.value = setflag( e.value, iname );				
				if ( r ) { //output
					if ( iname.charAt(0) != '_' ) oc.value = setflag( oc.value, iname );
					rc = _id( '_rows_changed_' + tn );
					if (rc) rc.value = setflag( rc.value, r );					
					if (p) p.classList.add('boxed');
				} else { //input
					if ( iname.charAt(0) != '_' ) ic.value = setflag( ic.value, iname );
					if ( i.classList.contains('key') ) 
						if ( iname.charAt(0) != '_' ) kc.value = setflag( kc.value, iname );
				}
			}
		}

		for( const p of document.querySelectorAll('.hand_picked') ) {
			let r = p.getAttribute('row');
			let tn = p.getAttribute('tablename');

			rc = _id( '_rows_changed_' + tn );
			if (rc) rc.value = setflag( rc.value, r );					
			if (p) p.classList.add('boxed');
		}
	}
	enable_buttons();
 }

var actionError = '';
var actionButton = null;

function submitWith( t ) { //for output
	if ( actionError ) { actionError = ''; return false; }
	if ( t.getAttribute('working') == '1' ) { alert('working...'); return false; }
	let c = window.getComputedStyle(t).color;
	if ( c == 'rgb(170, 170, 170)' && actionButton != t ) return false;

	let do_validations = t.getAttribute('do_validations');
	if ( do_validations && !validate_io() ) return false;

	let buttons = document.querySelectorAll( 'input[type=submit]' );
	for ( let button of buttons ) {
		button.setAttribute('working', 1);
		button.style.color = '#aaa';
	}
	t.style.color = '#000';
	t.setAttribute( 'label', t.value );
	t.value = 'working...';	
 }

function do_ajax( t ) { //for actions

	if ( t.getAttribute('working') == '1' ) { alert('working...'); return false; }
	let c = window.getComputedStyle(t).color;
	if ( c == 'rgb(170, 170, 170)' ) return false;
	let do_validations = t.getAttribute('do_validations');
	if ( do_validations && !validate_io() ) return false;	

	let buttons = document.querySelectorAll( 'input[type=submit]' );
	for ( let button of buttons ) {
		button.setAttribute('working', 1);
		button.prev_color = window.getComputedStyle( button ).color;
		button.style.color = '#aaa';
	}
	let cmd = t.id.substr(8);
	t.style.color = '#000';
	t.setAttribute( 'label', t.value );
	t.value = 'working...';	

	const f = document.querySelector('form');
	let p = new FormData(f);
	p.append('command_' + cmd, 'on' );
	p.append('_table_name', t.getAttribute('tablename') || 'null' );
	p.append("_ajax", cmd );	

	const request = new XMLHttpRequest();	
	request.open("POST", window.location, false); 	
	request.send( p );

	if ( request.status >= 200 && request.status < 300 ) {
		let out = request.responseText;		  		
		
		let i = out.indexOf( 'err:::' );
		if ( i >= 0 ) {
			i += 6;
			let j = out.indexOf( ':::', i );
			actionError = out.substring( i, j );
			_id('error_message').innerHTML = actionError;
			_id('_this_error').value = actionError;
		}

		i = out.indexOf( 'state:::' );
		if ( i >= 0 ) {
			i += 8;
			let j = out.indexOf( ':::', i );
			_id('_this_state').value = out.substring( i, j );
		}

		i = out.indexOf( 'notes:::' );
		if ( i >= 0 ) {
			i += 6;
			let j = out.indexOf( ':::', i );
			alert( out.substring( i, j ) );
		}

		i = out.indexOf( 'set:::' );
		while ( i >= 0 ) {
			i += 6;
			let j = out.indexOf( ':::', i );
			let action = out.substring( i, j );
			try {
				eval( action );
			} catch (x) {};
			i = out.indexOf( 'set:::', j );
		}
		if ( debugAppSettings.indexOf('ajax') >= 0 ) {
			let w = window.open();
			w.document.write(out);	  
		}
	}
	
	t.value = t.getAttribute( 'label' );	
	for ( let button of buttons ) {
		button.setAttribute('working', 0);
		button.style.color = button.prev_color;
	}

	enable_buttons();

	//enable submitWith, even is button is disabled, for this action
	actionButton = t;

	return true; 
 }

function validate_io(){
	
	//validate
	for ( const e of document.querySelectorAll( 'input[validate]' ) ) {
		let r = e.getAttribute( 'row' );
		let x = atob( e.getAttribute( 'validate' ) );
		
		//{$} => this.value
		// {$adjust_mark} => _id('adjust_mark_1').value
		x = x.replaceAll( '{$}', 'this.value' );
		x = x.replace( /\{\$(\w+)\}/, "_id('$1').value" );		

		let v = eval( x );
		if ( v !== true ) {
			let ie;
			ie = _id('error_' + e.id );	if (ie) ie.innerHTML = v;
			ie = _id('_this_error');	if (ie) ie.value = setflag( ie.value, e.name );								
			enable_buttons();
			return false;
		}
	}

	//filters already done by 'pattern'

	for ( const e of document.querySelectorAll( 'input[minmax]' ) ) {
		const v = e.getAttribute('minmax').split('-');
		if ( v && e.value && ( Number(e.value) < Number(v[0]) || Number(e.value) > Number(v[1]) ) ) {
			let ie = _id('error_' + e.id );
			ie.innerHTML = ' <font color=red>ERROR: must be between ' + v[0] + ' and ' + v[1] + '</font>';
			_id('_this_error').value = setflag( _id('_this_error').value, e.name );
			enable_buttons();
			return false;
		}
	}

	for ( const e of document.querySelectorAll( 'input[decimals]' ) ) {
		const d = e.getAttribute('decimals');
		const v = Number( e.value );
		if ( v && (v * Math.pow(10, d) % 1 !== 0) ) {
			let ie = _id('error_' + e.id );
			ie.innerHTML = ' <font color=red>ERROR: only ' + d + ' decimals allowed.</font>';
			_id('_this_error').value = setflag( _id('_this_error').value, e.name );
			enable_buttons();
			return false;
		}
	}


	_id('_this_error').value = '';
	enable_buttons();
	return true;
 }

function radio_picker( t, n ) {
	let e = _id( n );		
	let picked = e.value.indexOf( t.value ) >= 0;
	e.value = "";
	t.checked = false;
	if (!picked) {
		e.value = t.value;
		t.checked = true;
	}
	input_changed( e );
 }

function box_picker( t, n, m ) {			
	let e = _id( n );
	if (m) {
		let picked = t.classList.contains('boxed');
		let v = e.value ? "+" + e.value + "+" : '+';
		if ( picked ) {
			t.classList.remove('boxed');
			v = v.replace( '+' + t.getAttribute('value') + '+', '+' );
		} else {
			t.classList.add("boxed");
			v = v + t.getAttribute('value') + '+';
		}
		e.value = v.length > 1 ? v.substring(1, v.length-1 ) : '';
	} else {
		e.value = '';
		let picked = t.classList.contains('boxed');
		const pickers = document.querySelectorAll("[id^='" + n + "_picker']");
		for ( const picker of pickers ) picker.classList.remove('boxed');
		if ( !picked ) { t.classList.add("boxed"); e.value = t.getAttribute('value'); }
	}
	input_changed( e );
 }

function toggle_row_picker( t, r ) {
	let e = _id('_row_picked_' + t + '_' + r);
	e.classList.toggle('hand_picked');
	if ( ! e.classList.contains('hand_picked' ) ) {
		let q = `[tablename="${t}"][row="${r}"][value][value0]`;
		for( const i of  document.querySelectorAll(q) ) {
			i.value = i.getAttribute( 'value0' );
			i.classList.remove('changed');
		}
		//checkboxes needs special attention
		q = `[tablename="${t}"][row="${r}"][type="checkbox"][ivalue][value="Y/N"`;
		for( const i of  document.querySelectorAll(q) ) {
			i.checked = i.getAttribute('ivalue') == 'Y';			
			i.classList.remove('changed');
		}
	}
	input_changed();
 }

function postToNewWindow(url, data) { // written by AI, adapted by HI
    const windowName = 'post_target_' + Math.random().toString(36).substring(2);
    window.open('', windowName);
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.target = windowName; 
    const hiddenField = document.createElement('input');
	hiddenField.type = 'hidden';
	hiddenField.name = 'args';
    hiddenField.value = data;
	form.appendChild(hiddenField);    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}


