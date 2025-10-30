<h1>Faculty</h1>
<p>Will show different categories of faculty, teachers, staff, and non-teaching personnel.</p>

<h2>Create Faculty</h2>

<button onclick="document.getElementById('id01').style.display='block'" style="width:auto;">Add Faculty</button>

<div id="id01" class="modal" style="display:none;">
    <div class="imgcontainer">
      <span onclick="document.getElementById('id01').style.display='none'" class="close" title="Close Modal">&times;</span>
    </div>

    <div class="container">
      <label for="fname"><b>First Name</b></label><br>
      <input type="text" placeholder="First Name" name="fname" required><br><br>

      <label for="mname"><b>Middle Name</b></label><br>
      <input type="text" placeholder="Middle Name" name="mname" required><br><br>

      <label for="lname"><b>Last Name</b></label><br>
      <input type="text" placeholder="Last Name" name="lname" required><br><br>

      <label for="gender"><b>Gender</b></label><br>
      <input type="radio" name="gender" value="female"> Female
      <input type="radio" name="gender" value="male"> Male
      <input type="radio" name="gender" value="other"> Other
      <br><br>

      <label for="pnumber"><b>Phone</b></label><br>
      <input type="number" placeholder="Phone Number" name="pnumber" required><br><br>

      <label for="address"><b>Address</b></label><br>
      <input type="text" placeholder="Address" name="address" required><br><br>

      <label for="status"><b>Status</b></label><br>
      <input type="text" placeholder="Status" name="status" required><br><br>
      
      <button type="submit">Create</button><br><br>
    </div>

    <div class="container" style="background-color:#f1f1f1">
      <button type="button" onclick="document.getElementById('id01').style.display='none'" class="cancelbtn">Cancel</button>
    </div>
  </form>
</div>

<script>
  // Get the modal
  const modal = document.getElementById('id01');

  // When user clicks anywhere outside modal, close it
  window.onclick = function(event) {
    if (event.target == modal) {
      modal.style.display = "none";
    }
  }
</script>
