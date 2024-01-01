[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate/?business=SAT23GPU7F6AS&no_recurring=1&currency_code=EUR)
# Community Builder user list module

## Description
The purpose of this module is to present a Community Builder list of Users with a custom presentation-template with the possibility of using/presenting all user fields from CB and Joomla.
This is a fork of https://github.com/magnushasselquist/hqcblistmodule

You can also find it on joomla: https://extensions.joomla.org/extension/extension-specific/community-builder-extensions/community-builder-list/

## Examples
[Demo website](https://marijqg132.132.axc.nl/demo/)  
Example of presentation in front-end:  
![cblistfront](https://user-images.githubusercontent.com/23451105/120665837-6a21d600-c48c-11eb-9815-c243f2310b37.png)

Back-end configuration:
![config](https://github.com/Tazzios/cblistmodule/assets/23451105/9f0f5639-8138-4484-9b43-bb955efdd57d)

## Configuration  
The only mandatory configuration for the module is selecting a CB list to show the users from. all other settings have default options which you can change.

### template examples:
``` html
<div class="yourclasstostyle"><p>[firstname] [lastname]<br/>[cb_yourfiled]</p></div>
<div class="yourclasstostyle">[avatar]<br /> <a href="cb-profile/[user_id]">[Name]</a>
<div class="role"><a href="departmens/[cb_department]">[cb_department]</a>,[cb_role]</div>
```
### rule examples;
A basic set of rules will be created when creating the module.
When creating custom tags make sure the tags that you are using within always have a value. For example see the avatar rule and show_avatar rule.
  
You can also show fields only to specific usergroups. 
For existing databace fields like cb_example you can also create a rule to set autorisation on by creatign the following rule:
tag name:  cb_example
Usergroup: to what you want
htmlcode to replace tag with: [cb_example]
