#autoload

composer组件下载器 php版，免去下载安装composer的烦恼 。

##使用方法

和composer大致相同，把autoload.php扔到vendor目录后，

在页面上引用autoload.php，之后访问页面首先是会进入安装界面自动下载依赖包。

等下载完成后点击“安装完成”后就会生成composer.lock，下次就不会进入下载界面了。

目前状态：开发中


##开发记录
 
[BUG] 可选的下载版本（当前始终下载最新版本

[ADD] 可选的下载源（当前为github下载
 
[ADD] 可选确认是否需要下载dev版 

[ADD] 安装完成后删除全部zip包

[ADD] 缓存class映射关系

