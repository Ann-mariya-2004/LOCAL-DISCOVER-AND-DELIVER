<?php

include "db.php";


$name = $_POST["name"];
$description = $_POST["description"];
$price = $_POST["price"];
$category = $_POST["category"];



$image_name = "";


if(isset($_FILES["image"])){

    $image_name = time()."_".$_FILES["image"]["name"];

    $path = "../uploads/".$image_name;

    move_uploaded_file(
        $_FILES["image"]["tmp_name"],
        $path
    );

}



$sql = "INSERT INTO food_items
(name,description,price,image,category)

VALUES

('$name','$description','$price','$image_name','$category')";



if(mysqli_query($conn,$sql)){


echo "

<script>

alert('Food Added Successfully');

window.location='../food.php';

</script>

";


}
else{


echo mysqli_error($conn);


}


?>