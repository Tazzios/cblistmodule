
# Community Builder user list module

## Description
The purpose of this module is to present a Community Builder list of Users with a custom presentation-template with the possibility of using/presenting all user fields from CB and Joomla.
This is a fork of https://github.com/magnushasselquist/hqcblistmodule

## Screenshots

Example of presentation in front-end:  
![cblistfront](https://user-images.githubusercontent.com/23451105/120665837-6a21d600-c48c-11eb-9815-c243f2310b37.png)

Back-end configuration:
![cblistbackend](https://user-images.githubusercontent.com/23451105/120667634-f84a8c00-c48d-11eb-9cd5-a8e6279bb936.png)

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
