describe('Social app setup', function() {

	before(function() {
		cy.login('admin', 'admin')
	})

	it('See the welcome message', function() {
		cy.visit('/apps/social/')
		cy.get('.social__welcome').should('contain', 'Nextcloud becomes part of the federated social networks!')
		cy.get('.social__welcome').find('.icon-close').click()
		cy.get('.social__welcome').should('not.exist')
	})

	it('See the .well-known setup error', function() {
		cy.visit('/apps/social/')
		cy.window().then((win) => {
			if (win.oc_isadmin) {
				//cy.get('.setup').should('contain', '.well-known/webfinger isn\'t properly set up!')
			}
		})
	})

	it('See the empty content illustration', function() {
		//cy.get('#app-navigation').contains('Home').click()
		//cy.get('.emptycontent').should('be.visible')
		cy.get('#app-navigation').contains('Direct messages').click()
		cy.get('.emptycontent').should('be.visible').contains('No direct messages found')
		cy.get('#app-navigation').contains('Profile').click()
		cy.get('.emptycontent').should('be.visible').contains('No posts found')
	})

})
