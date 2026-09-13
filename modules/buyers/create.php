<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Buyer | ThreadFlow</title>
</head>
<body>
    <h1>Add Buyer</h1>

    <form action="store.php" method="post">
        <p>
            <label>Buyer Name *</label><br>
            <input type="text" name="buyer_name" maxlength="150" required>
        </p>

        <p>
            <label>Contact Person</label><br>
            <input type="text" name="contact_person" maxlength="100">
        </p>

        <p>
            <label>Email</label><br>
            <input type="email" name="email" maxlength="150">
        </p>

        <p>
            <label>Phone</label><br>
            <input type="text" name="phone" maxlength="30">
        </p>

        <p>
            <label>Country</label><br>
            <input type="text" name="country" maxlength="100">
        </p>

        <p>
            <label>Address</label><br>
            <textarea name="address" rows="3"></textarea>
        </p>

        <button type="submit">Save Buyer</button>
    </form>
</body>
</html>