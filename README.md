# piklist-autocomplete

## About:

piklist-autocomplete is a wordpress plugin that adds autocomplete functionality to piklist, with and without AJAX support.

To remotely load data it uses the wordpress REST API. However you could configure it to use a custom url to retrieve the data.

## How to use

If you have a piklist field like this:

```php
piklist('field', array(
  'type' => 'select',
  ...
```
just changing the type to `autocomplete` should work:

```php
piklist('field', array(
  'type' => 'autocomplete',
  ...
```

However, the true power of the plugin is shown when you remove the `choices` parameter and you let the autocomplete field automatically populate the choices array. In order for this to work, you need to enable the REST API (it's enabled by default in Wordpress).

For a custom post type, you'll need to register the type with `'show_in_rest' => true` and specify your custom post type in the `'query'` array, under the `'autocomplete'` parameters:

```php
piklist('field', array(
  'type' => 'autocomplete',
  ...
  'autocomplete' => array(
    'query' => array(
      'post_type' => 'my_custom_post_type'
    ),
```
If you prefer another value to be displayed instead of the post_title for posts, name for users, etc you can set the `'display_field_name'` option, under the `'config'` parameters:

```php
piklist('field', array(
  'type' => 'autocomplete',
  ...
  'autocomplete' => array(
    'config' => array(
      'display_field_name' => 'field_name',
    ),
    'query' => array(
      'post_type' => 'my_custom_post_type'
    ),
```

## Displaying fields not returned by the REST API

For a custom post type, you can easily display a custom field instead of the default post_title field using the `'display_field_name'` option and extending the data returned by the REST API:

```php
add_action('rest_api_init', 'piklist_autocomplete_register_field');

function piklist_autocomplete_register_field() {
    register_rest_field('my_custom_post_type', 'my_custom_field',
        array(
            'get_callback' => 'piklist_autocomplete_get_custom_field',
            'update_callback' => null,
            'schema' => null,
        )
    );
}

function piklist_autocomplete_get_custom_field($object, $field_name, $request) {
	return get_post_meta($object['id'], $field_name, true);
}
```

## History:
* 10/01/2018: v0.0.1 released as a proof of concept
