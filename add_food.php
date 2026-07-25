<!DOCTYPE html>
<html>

<head>

<title>LDD | Add Food</title>

<style>

body{
    font-family:Arial;
    background:linear-gradient(135deg,#0f766e,#14b8a6);
    padding:30px;
}


.container{

    width:500px;
    margin:auto;
    background:white;
    padding:30px;
    border-radius:15px;

}


h2{

text-align:center;
color:#134e4a;

}


input,textarea{

width:100%;
padding:12px;
margin:10px 0;
border-radius:8px;
border:1px solid #ccc;

}


button{

width:100%;
padding:12px;
background:#0f766e;
color:white;
border:none;
border-radius:8px;
font-weight:bold;

}


</style>

</head>


<body>


<div class="container">


<h2>
Add Food Item
</h2>



<form action="backend/add_food.php" method="POST" enctype="multipart/form-data">


<input 
type="text"
name="name"
placeholder="Food Name"
required>



<textarea
name="description"
placeholder="Description">
</textarea>



<input
type="number"
name="price"
placeholder="Price"
required>



<input
type="text"
name="category"
placeholder="Category">



<input
type="file"
name="image"
accept="image/*">



<button type="submit">

Add Food

</button>



</form>


</div>


</body>

</html>