<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body {
  font-family: Arial, Helvetica, sans-serif;
  background-color: #f2f2f2;
  margin: 0;
  padding: 0;
}

* { box-sizing: border-box; }

/* Input fields */
input[type=text], input[type=password], input[type=number] {
  width: 100%;
  padding: 12px;
  margin: 6px 0 14px 0;
  border: 1px solid #ccc;
  border-radius: 4px;
  background: #f9f9f9;
}

input:focus {
  background-color: #fff;
  outline: none;
  border-color: #04AA6D;
}

/* Buttons */
button {
  background-color: #04AA6D;
  color: white;
  padding: 12px 18px;
  margin: 8px 0;
  border: none;
  cursor: pointer;
  width: 100%;
  border-radius: 4px;
  font-size: 16px;
}

button:hover {
  opacity: 0.9;
}

.cancelbtn {
  background-color: #f44336;
}

/* Container */
.container {
  padding: 16px;
}

/* Modal */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0,0,0,0.6);
  padding-top: 40px;
}

/* Modal Content */
.modal-content {
  background-color: #fefefe;
  margin: auto;
  border: 1px solid #888;
  width: 90%;
  max-width: 600px;
  border-radius: 6px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.2);
  overflow: hidden;
}

/* Header bar */
.imgcontainer {
  background: #04AA6D;
  color: white;
  padding: 12px 20px;
  position: relative;
  text-align: center;
}

.imgcontainer h2 {
  margin: 0;
  font-size: 20px;
}

/* Close button */
.close {
  position: absolute;
  right: 18px;
  top: 8px;
  font-size: 28px;
  font-weight: bold;
  color: #fff;
  cursor: pointer;
}

.close:hover {
  color: #ffdddd;
}

/* Responsive */
@media screen and (max-width: 420px) {
  .modal-content { width: 95%; }
  .cancelbtn, .signupbtn {
     width: 100%;
  }
}
</style>
</head>
<body>

<h1>Faculty</h1>
<p>Will show different categories of faculty, teachers, staff, and non-teaching personnel.</p>

<button onclick="document.getElementById('id01').style.display='block'" style="width:auto;">Add Faculty</button>

<!-- Modal -->
<div id="id01" class="modal">
  <form class="modal-content animate" action="/action_page.php" method="post">
    <div class="imgcontainer">
      <span onclick="document.getElementById('id01').style.display='none'" class="close" title="Close">&times;</span>
      <h2>Create Faculty</h2>
    </div>

    <div class="container">
      <label for="fname"><b>First Name</b></label>
      <input type="text" placeholder="First Name" name="fname" required>

      <label for="mname"><b>Middle Name</b></label>
      <input type="text" placeholder="Middle Name" name="mname" required>

      <label for="lname"><b>Last Name</b></label>
      <input type="text" placeholder="Last Name" name="lname" required>

      <label><b>Gender</b></label><br>
      <label><input type="radio" name="gender" value="female"> Female</label>
      <label><input type="radio" name="gender" value="male"> Male</label>
      <label><input type="radio" name="gender" value="other"> Other</label>
      <br><br>

      <label for="pnumber"><b>Phone</b></label>
      <input type="number" placeholder="Phone Number" name="pnumber" required>

      <label for="address"><b>Address</b></label>
      <input type="text" placeholder="Address" name="address" required>

      <label for="status"><b>Status</b></label>
      <input type="text" placeholder="Status" name="status" required>

      <button type="submit">Create</button>
    </div>

    <div class="container" style="background-color:#f1f1f1">
      <button type="button" onclick="document.getElementById('id01').style.display='none'" class="cancelbtn">Cancel</button>
    </div>
  </form>
</div>

<script>
// Close modal if user clicks outside it
window.onclick = function(event) {
  const modal = document.getElementById('id01');
  if (event.target == modal) {
    modal.style.display = "none";
  }
}
</script>

</body>
</html>