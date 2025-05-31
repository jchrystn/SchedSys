const inputs = document.querySelectorAll("input");
const button = document.querySelector("button");

inputs.forEach((input, index) => {
    input.addEventListener("keyup", (e) => {
        const currentInput = input;
        const nextInput = input.nextElementSibling;
        const prevInput = input.previousElementSibling;

        if (currentInput.value.length > 1) {
            currentInput.value = currentInput.value[0]; // Keep only the first character
            return;
        }

        if (e.key === "Backspace") {
            if (currentInput.value === "") {
                if (prevInput) {
                    prevInput.focus();
                    if (prevInput.value !== "") {
                        prevInput.removeAttribute("disabled");
                    }
                }
                if (!inputs[inputs.length - 1].disabled) {
                    button.classList.remove("active");
                }
                return;
            } else if (prevInput && prevInput.value !== "") {
                currentInput.value = ""; // Clear the current input
                return;
            }
        }

        if (nextInput && currentInput.value !== "") {
            nextInput.removeAttribute("disabled");
            nextInput.focus();
        }

        if (!inputs[inputs.length - 1].disabled && inputs[inputs.length - 1].value !== "") {
            button.classList.add("active");
        } else {
            button.classList.remove("active");
        }
    });
});

window.addEventListener("load", () => inputs[0].focus());


