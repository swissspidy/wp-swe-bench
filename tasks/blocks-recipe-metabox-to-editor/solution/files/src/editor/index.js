/**
 * "Recipe details" panel in the document sidebar of the block editor.
 *
 * Edits the same post meta the classic meta box used to edit.
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel, store as editorStore } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Flex,
	FlexBlock,
	FlexItem,
	SelectControl,
	TextControl,
	TextareaControl,
	BaseControl,
} from '@wordpress/components';

import './editor.scss';

const KEYS = {
	ingredients: '_acme_recipe_ingredients',
	prep: '_acme_recipe_prep_time',
	cook: '_acme_recipe_cook_time',
	servings: '_acme_recipe_servings',
	difficulty: '_acme_recipe_difficulty',
	notes: '_acme_recipe_notes',
	staffPick: '_acme_recipe_staff_pick',
};

const settings = window.acmeRecipesEditor || { canStaffPick: false, difficulties: {} };

const toInt = ( value ) => {
	const n = parseInt( value, 10 );
	return isNaN( n ) || n < 0 ? 0 : n;
};

function IngredientsEditor( { ingredients, onChange } ) {
	// Rows are edited locally so that half-filled rows (no ingredient name yet) don't end up in the meta.
	const [ rows, setRows ] = useState( () => ingredients.map( ( i ) => ( { ...i } ) ) );

	useEffect( () => {
		const complete = rows
			.filter( ( r ) => ( r.item || '' ).trim() !== '' )
			.map( ( r ) => ( { amount: r.amount || '', unit: r.unit || '', item: r.item } ) );
		if ( JSON.stringify( complete ) !== JSON.stringify( ingredients ) ) {
			onChange( complete );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ rows ] );

	const update = ( index, field, value ) =>
		setRows( rows.map( ( row, i ) => ( i === index ? { ...row, [ field ]: value } : row ) ) );

	return (
		<BaseControl __nextHasNoMarginBottom label={ __( 'Ingredients', 'acme-recipes' ) } id="acme-recipe-ingredients">
			<div className="acme-recipe-panel__ingredients">
				{ rows.map( ( row, index ) => (
					<Flex key={ index } className="acme-recipe-panel__ingredient" align="flex-end">
						<FlexItem>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Amount', 'acme-recipes' ) }
								value={ row.amount || '' }
								onChange={ ( value ) => update( index, 'amount', value ) }
							/>
						</FlexItem>
						<FlexItem>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Unit', 'acme-recipes' ) }
								value={ row.unit || '' }
								onChange={ ( value ) => update( index, 'unit', value ) }
							/>
						</FlexItem>
						<FlexBlock>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Ingredient', 'acme-recipes' ) }
								value={ row.item || '' }
								onChange={ ( value ) => update( index, 'item', value ) }
							/>
						</FlexBlock>
						<FlexItem>
							<Button
								size="small"
								icon="no-alt"
								isDestructive
								label={ __( 'Remove ingredient', 'acme-recipes' ) }
								/* translators: %s: ingredient */
								describedBy={ sprintf( __( 'Remove %s', 'acme-recipes' ), row.item || '' ) }
								onClick={ () => setRows( rows.filter( ( r, i ) => i !== index ) ) }
							/>
						</FlexItem>
					</Flex>
				) ) }
				<Button variant="secondary" onClick={ () => setRows( [ ...rows, { amount: '', unit: '', item: '' } ] ) }>
					{ __( 'Add ingredient', 'acme-recipes' ) }
				</Button>
			</div>
		</BaseControl>
	);
}

function RecipeDetailsPanel() {
	const { postType, meta } = useSelect( ( select ) => {
		const editor = select( editorStore );
		return {
			postType: editor.getCurrentPostType(),
			meta: editor.getEditedPostAttribute( 'meta' ) || {},
		};
	}, [] );
	const { editPost } = useDispatch( editorStore );

	if ( postType !== 'acme_recipe' ) {
		return null;
	}

	const setMeta = ( key, value ) => editPost( { meta: { [ key ]: value } } );
	const difficulties = Object.entries( settings.difficulties || {} ).map( ( [ value, label ] ) => ( { value, label } ) );

	return (
		<PluginDocumentSettingPanel name="acme-recipe-details" title={ __( 'Recipe details', 'acme-recipes' ) } className="acme-recipe-panel">
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				type="number"
				min={ 0 }
				label={ __( 'Prep time (minutes)', 'acme-recipes' ) }
				value={ meta[ KEYS.prep ] ?? 0 }
				onChange={ ( value ) => setMeta( KEYS.prep, toInt( value ) ) }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				type="number"
				min={ 0 }
				label={ __( 'Cook time (minutes)', 'acme-recipes' ) }
				value={ meta[ KEYS.cook ] ?? 0 }
				onChange={ ( value ) => setMeta( KEYS.cook, toInt( value ) ) }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				type="number"
				min={ 0 }
				max={ 100 }
				label={ __( 'Servings', 'acme-recipes' ) }
				value={ meta[ KEYS.servings ] ?? 0 }
				onChange={ ( value ) => setMeta( KEYS.servings, Math.min( 100, toInt( value ) ) ) }
			/>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Difficulty', 'acme-recipes' ) }
				value={ meta[ KEYS.difficulty ] || '' }
				options={ [ { value: '', label: __( '— Select —', 'acme-recipes' ) }, ...difficulties ] }
				onChange={ ( value ) => setMeta( KEYS.difficulty, value ) }
			/>
			<IngredientsEditor
				ingredients={ Array.isArray( meta[ KEYS.ingredients ] ) ? meta[ KEYS.ingredients ] : [] }
				onChange={ ( value ) => setMeta( KEYS.ingredients, value ) }
			/>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Kitchen notes', 'acme-recipes' ) }
				help={ __( 'Private: never shown on the site.', 'acme-recipes' ) }
				value={ meta[ KEYS.notes ] || '' }
				onChange={ ( value ) => setMeta( KEYS.notes, value ) }
			/>
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ __( 'Staff pick', 'acme-recipes' ) }
				checked={ !! meta[ KEYS.staffPick ] }
				disabled={ ! settings.canStaffPick }
				help={ settings.canStaffPick ? undefined : __( 'Only editors can change this.', 'acme-recipes' ) }
				onChange={ ( value ) => setMeta( KEYS.staffPick, value ) }
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'acme-recipe-details', { render: RecipeDetailsPanel } );
