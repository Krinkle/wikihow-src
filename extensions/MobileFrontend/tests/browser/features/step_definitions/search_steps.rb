When(/^I click the placeholder search box$/) do
  on(ArticlePage).search_box_placeholder_element.when_present.click
  # this check is needed to accommodate for the hack for opening the virtual
  # keyboard (see comments in search.js)
  on(ArticlePage).wait_until do
    on(ArticlePage).current_url.end_with? '#/search'
  end
end

When(/^I type into search box "(.+)"$/) do |search_term|
  on(ArticlePage).search_box2=search_term
end

Then(/^Search results should contain (.+)$/) do |text|
  on(ArticlePage).search_result_element.when_present.text.should == text
end

Then(/^I see the search overlay$/) do
  on(ArticlePage).search_overlay_element.should be_visible
end

When(/^I click the search overlay close button$/) do
  on(ArticlePage).search_overlay_close_button_element.click
end

Then(/^I don't see the search overlay$/) do
  on(ArticlePage).search_overlay_element.should_not be_visible
end

When(/^I click a search result$/) do
  on(ArticlePage).search_result_element.when_present.click
end
