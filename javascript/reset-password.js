let togglepassword1 = document.getElementById("togglepassword1");
var cpassword = document.getElementById("cpassword");

togglepassword1.onclick = function(){
    if(cpassword.type === "password"){
        cpassword.type = "text";
    }else{
        cpassword.type = "password";
    }
}

function validateForm() {
    var password = document.forms["reset_pass"]["password"].value;
    var confirm_password = document.forms["reset_pass"]["cpassword"].value;

    if (password !== confirm_password) {
        alert("Passwords do not match");
        return false;
    }

    // var passwordPattern = /^(?=.*[0-9])(?=.*[!@#$%^&*])(?=.{8,20})/;
    // if (!passwordPattern.test(password)) {
    //     alert("Password must be 8-20 characters and contain at least one number and one special character.");
    //     return false;
    // }
    // return true;
}
