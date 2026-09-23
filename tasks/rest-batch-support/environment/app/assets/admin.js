/**
 * Acme Tasks admin screen.
 *
 * Deliberately dependency-free (no build step): talks to the plugin's REST API through
 * wp.apiFetch. Still uses the pre-1.3 `completed` flag when toggling tasks.
 */
( function ( wp, config ) {
	const { __, sprintf } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const ns = '/' + config.namespace;

	const state = {
		lists: [],
		listId: null,
		tasks: [],
		busy: false,
	};

	let root;

	function el( tag, attrs = {}, children = [] ) {
		const node = document.createElement( tag );
		Object.entries( attrs ).forEach( ( [ key, value ] ) => {
			if ( key === 'text' ) {
				node.textContent = value;
			} else if ( key.startsWith( 'on' ) ) {
				node.addEventListener( key.slice( 2 ).toLowerCase(), value );
			} else if ( value === true ) {
				node.setAttribute( key, '' );
			} else if ( value !== false && value !== null && value !== undefined ) {
				node.setAttribute( key, value );
			}
		} );
		children.forEach( ( child ) => child && node.appendChild( child ) );
		return node;
	}

	function notice( message, type = 'error' ) {
		const box = root.querySelector( '.acme-tasks-notice' );
		box.className = 'acme-tasks-notice notice notice-' + type;
		box.textContent = message;
		box.hidden = ! message;
	}

	async function run( fn ) {
		if ( state.busy ) {
			return;
		}
		state.busy = true;
		root.classList.add( 'is-busy' );
		try {
			notice( '' );
			await fn();
		} catch ( error ) {
			notice( error && error.message ? error.message : __( 'Something went wrong.', 'acme-tasks' ) );
		} finally {
			state.busy = false;
			root.classList.remove( 'is-busy' );
			render();
		}
	}

	async function loadLists() {
		state.lists = await apiFetch( { path: ns + '/lists' } );
		if ( ! state.lists.find( ( l ) => l.id === state.listId ) ) {
			state.listId = state.lists.length ? state.lists[ 0 ].id : null;
		}
		await loadTasks();
	}

	async function loadTasks() {
		state.tasks = state.listId ? await apiFetch( { path: ns + '/lists/' + state.listId + '/tasks' } ) : [];
	}

	function addTask( title, dueDate ) {
		return run( async () => {
			const data = { title };
			if ( dueDate ) {
				data.due_date = dueDate;
			}
			await apiFetch( { path: ns + '/lists/' + state.listId + '/tasks', method: 'POST', data } );
			await loadTasks();
		} );
	}

	function toggleTask( task ) {
		return run( async () => {
			await apiFetch( { path: ns + '/tasks/' + task.id, method: 'PATCH', data: { completed: ! task.completed } } );
			await loadTasks();
		} );
	}

	function moveTask( task, delta ) {
		return run( async () => {
			const position = Math.max( 0, task.position + delta );
			await apiFetch( { path: ns + '/tasks/' + task.id, method: 'PATCH', data: { position } } );
			await loadTasks();
		} );
	}

	function deleteTask( task ) {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( sprintf( __( 'Delete "%s"?', 'acme-tasks' ), task.title ) ) ) {
			return;
		}
		return run( async () => {
			const result = await apiFetch( { path: ns + '/tasks/' + task.id, method: 'DELETE' } );
			if ( ! result || ! result.deleted ) {
				throw new Error( __( 'The task could not be deleted.', 'acme-tasks' ) );
			}
			await loadTasks();
		} );
	}

	function sortByDueDate() {
		return run( async () => {
			const order = [ ...state.tasks ]
				.sort( ( a, b ) => ( a.due_date || '9999-99-99' ).localeCompare( b.due_date || '9999-99-99' ) )
				.map( ( t ) => t.id );
			state.tasks = await apiFetch( { path: ns + '/lists/' + state.listId + '/reorder', method: 'POST', data: { order } } );
		} );
	}

	function createList( title, color ) {
		return run( async () => {
			const list = await apiFetch( { path: ns + '/lists', method: 'POST', data: { title, color } } );
			state.listId = list.id;
			await loadLists();
		} );
	}

	function userName( id ) {
		const user = config.users.find( ( u ) => u.id === id );
		return user ? user.name : '';
	}

	function renderTask( task, index ) {
		return el( 'li', { class: 'acme-task' + ( task.completed ? ' is-done' : '' ), 'data-id': task.id }, [
			el( 'input', {
				type: 'checkbox',
				class: 'acme-task-toggle',
				checked: task.completed,
				'aria-label': sprintf( __( 'Mark "%s" as done', 'acme-tasks' ), task.title ),
				onChange: () => toggleTask( task ),
			} ),
			el( 'span', { class: 'acme-task-title', text: task.title } ),
			task.due_date ? el( 'span', { class: 'acme-task-due', text: task.due_date } ) : null,
			task.assignee ? el( 'span', { class: 'acme-task-assignee', text: userName( task.assignee ) } ) : null,
			el( 'button', { type: 'button', class: 'button-link acme-task-up', disabled: index === 0, 'aria-label': __( 'Move up', 'acme-tasks' ), text: '↑', onClick: () => moveTask( task, -1 ) } ),
			el( 'button', { type: 'button', class: 'button-link acme-task-down', disabled: index === state.tasks.length - 1, 'aria-label': __( 'Move down', 'acme-tasks' ), text: '↓', onClick: () => moveTask( task, 1 ) } ),
			el( 'button', { type: 'button', class: 'button-link button-link-delete acme-task-delete', text: __( 'Delete', 'acme-tasks' ), onClick: () => deleteTask( task ) } ),
		] );
	}

	function render() {
		const noticeBox = root.querySelector( '.acme-tasks-notice' );
		root.textContent = '';
		root.appendChild( noticeBox || el( 'div', { class: 'acme-tasks-notice', hidden: true } ) );

		const select = el(
			'select',
			{
				class: 'acme-tasks-list-select',
				'aria-label': __( 'Task list', 'acme-tasks' ),
				onChange: ( e ) => run( async () => {
					state.listId = Number( e.target.value );
					await loadTasks();
				} ),
			},
			state.lists.map( ( l ) => el( 'option', { value: l.id, selected: l.id === state.listId, text: l.title } ) )
		);

		const newList = el( 'form', { class: 'acme-tasks-new-list', onSubmit: ( e ) => {
			e.preventDefault();
			const title = e.target.querySelector( '[name=list_title]' ).value.trim();
			if ( title ) {
				createList( title, e.target.querySelector( '[name=list_color]' ).value );
			}
		} }, [
			el( 'input', { type: 'text', name: 'list_title', placeholder: __( 'New list', 'acme-tasks' ) } ),
			el( 'input', { type: 'color', name: 'list_color', value: '#3858e9' } ),
			el( 'button', { type: 'submit', class: 'button', text: __( 'Create list', 'acme-tasks' ) } ),
		] );

		root.appendChild( el( 'div', { class: 'acme-tasks-toolbar' }, [ select, newList ] ) );

		if ( ! state.listId ) {
			root.appendChild( el( 'p', { text: __( 'No task lists yet.', 'acme-tasks' ) } ) );
			return;
		}

		const addForm = el( 'form', { class: 'acme-tasks-add', onSubmit: ( e ) => {
			e.preventDefault();
			const title = e.target.querySelector( '#acme-task-new-title' ).value.trim();
			if ( title ) {
				addTask( title, e.target.querySelector( '#acme-task-new-due' ).value );
			}
		} }, [
			el( 'label', { for: 'acme-task-new-title', class: 'screen-reader-text', text: __( 'New task', 'acme-tasks' ) } ),
			el( 'input', { type: 'text', id: 'acme-task-new-title', placeholder: __( 'What needs to be done?', 'acme-tasks' ) } ),
			el( 'input', { type: 'date', id: 'acme-task-new-due', 'aria-label': __( 'Due date', 'acme-tasks' ) } ),
			el( 'button', { type: 'submit', class: 'button button-primary', text: __( 'Add task', 'acme-tasks' ) } ),
			el( 'button', { type: 'button', class: 'button acme-tasks-sort', text: __( 'Sort by due date', 'acme-tasks' ), onClick: sortByDueDate } ),
		] );
		root.appendChild( addForm );
		root.appendChild( el( 'ul', { class: 'acme-tasks-items' }, state.tasks.map( renderTask ) ) );
	}

	wp.domReady( () => {
		root = document.getElementById( 'acme-tasks-app' );
		if ( ! root ) {
			return;
		}
		root.textContent = '';
		root.appendChild( el( 'div', { class: 'acme-tasks-notice', hidden: true } ) );
		run( loadLists );
	} );
}( window.wp, window.acmeTasks ) );
