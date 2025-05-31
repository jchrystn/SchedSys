<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Image to Excel</title>
    <!-- Include SheetJS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <!-- Include XLSX-Populate for adding images -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx-populate/browser/xlsx-populate.min.js"></script>
</head>
<body>
    <h1>Export Image to Excel</h1>
    <button id="exportButton">Export to Excel</button>

    <script>
        document.getElementById("exportButton").addEventListener("click", async function () {
            // Replace with the actual image URL
            const imageUrl = "https://via.placeholder.com/150"; // Example image
            try {
                // Step 1: Fetch the image and convert to base64
                const response = await fetch(imageUrl);
                const blob = await response.blob();
                const reader = new FileReader();

                reader.onload = async function () {
                    const base64Image = reader.result.split(",")[1]; // Extract base64 content

                    // Step 2: Create a blank workbook using Xlsx-Populate
                    const workbook = await XlsxPopulate.fromBlankAsync();

                    // Step 3: Insert the image into the workbook
                    workbook.sheet(0).image({
                        base64: base64Image,
                        name: "Embedded Image",
                        pos: { row: 1, col: 1 }, // Position (A1 cell)
                        scale: 0.5, // Scale factor (optional)
                    });

                    // Step 4: Export and download the workbook
                    const blobData = await workbook.outputAsync();
                    const a = document.createElement("a");
                    a.href = URL.createObjectURL(blobData);
                    a.download = "ImageExport.xlsx"; // File name
                    a.click();
                };

                reader.readAsDataURL(blob); // Convert image blob to Base64
            } catch (error) {
                console.error("Error exporting image to Excel:", error);
            }
        });
    </script>
</body>
</html>
