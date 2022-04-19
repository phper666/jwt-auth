## v4.0.7
### Fixed
- jwt payload的aud字段只支持数组类型
- 更新readme文档

## v4.0.6
### Feat
- 新增jwt可以自定义payload的sub、iss、aud字段

## v4.0.5
### Fixed
- [#51](https://github.com/phper666/jwt-auth/issues/51) 修复单点登录重新获取token，没有使原有用户token失效的问题
- 优化部分代码逻辑
