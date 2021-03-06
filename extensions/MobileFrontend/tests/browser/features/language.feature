# FIXME: this assumes that the main page has more than one language
@en.m.wikipedia.beta.wmflabs.org @en.m.wikipedia.org @test2.m.wikipedia.org
Feature: Language selection

  Background:
    Given I am on the home page
    When I click the language button

  Scenario: Opening language overlay
    Then I see the language overlay

  Scenario: Closing language overlay (overlay button)
    When I click the language overlay close button
    Then I don't see the languages overlay

  Scenario: Closing language overlay (browser button)
    When I click the browser back button
    Then I don't see the languages overlay
