<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>又来试探死亡边缘了？</title>
</head>
<body>

<form action="file" enctype="multipart/form-data" method="post">
    {{csrf_field()}}
    请选择需要上传的文件：
    <input type="file" name="upfile"/><br>
    <input type="submit" value="上传"/>
</form>


</body>
</html>
