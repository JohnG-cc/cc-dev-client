# Reference Client

This php application implements the [Credit Commons protocol](https://gitlab.com/credit-commons-software-stack/cc-php-lib/-/blob/master/docs/credit-commons-openapi-3.0.yml) from the client side and was developed in tandem with the reference node. Playing the role of 'leaf' in a credit commons tree, it stores no data except perhaps login credentials.

Each API method is shown as a tab with a form, allowing the user to make most possible API requests. The request is then printed to screen along with the response. Some of the responses are rendered as raw json, others a bit more visually.
