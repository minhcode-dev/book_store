    function toggleDropdown() {
        var dropdown = document.getElementById("userDropdown");
        dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
    }

    // Tắt dropdown khi click ngoài vùng dropdown
    document.addEventListener("click", function(event) {
        var dropdown = document.getElementById("userDropdown");
        var toggle = document.querySelector(".dropdown");

        if (!toggle.contains(event.target)) {
            dropdown.style.display = "none";
        }
    });
