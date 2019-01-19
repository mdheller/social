import 'cypress-testing-library/add-commands'

Cypress.Commands.add('login', (user, password, route = '/apps/files') => {
	cy.clearCookies()
	Cypress.Cookies.defaults({
		whitelist: /^(oc|nc)/
	})
	cy.visit(route)
	cy.get('input[name=user]').type(user)
	cy.get('input[name=password]').type(password)
	cy.get('input#submit').click()
	cy.url().should('include', route)
})

/**
 * Create a user using the provision API
 */
Cypress.Commands.add('nextcloudCreateUser', (user, password) => {
	cy.clearCookies()
	var url = Cypress.config('baseUrl').replace(/\/index.php\/?$/g, '')
	cy.request({
		method: 'POST',
		url: `${url}/ocs/v1.php/cloud/users?format=json`,
		form: true,
		body: {
			userid: user,
			password: password
		},
		auth: { 'user': 'admin', 'pass': 'admin' },
		headers: { 'OCS-ApiRequest': 'true', 'Content-Type': 'application/x-www-form-urlencoded', 'Authorization': 'Basic YWRtaW46YWRtaW4=' }

	}).then((response) => {
		// eslint-disable-next-line
		console.log(response)
	})
})
