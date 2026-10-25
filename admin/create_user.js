function toggleFormSections() {
    const selectedType = document.getElementById('user_type').value;
   
    // Reset and hide all sub-sections
    document.querySelectorAll('.subtype-section').forEach(section => {
        section.style.display = 'none';
        section.querySelectorAll('input, select').forEach(input => {
            input.removeAttribute('required');
        });
    });

    // Make target block visible and assign required statuses
    if (selectedType) {
        const activeSection = document.getElementById('section_' + selectedType);
        if (activeSection) {
            activeSection.style.display = 'block';
           
            // Sync form rules with your SQL and PHP validation rules
            if (selectedType === 'Admin') {
                document.getElementById('security_level').setAttribute('required', 'required');
            } else if (selectedType === 'Student') {
                document.getElementById('student_year').setAttribute('required', 'required');
                document.getElementById('student_level').setAttribute('required', 'required');
            } else if (selectedType === 'Faculty') {
                document.getElementById('faculty_status').setAttribute('required', 'required');
            }
        }
    }
    toggleStudentSublevels();
}

function toggleStudentSublevels() {
    const userType = document.getElementById("user_type").value;
    const level = document.getElementById("student_level").value;

    const undergradFields = document.getElementById("undergrad_fields");
    const gradFields = document.getElementById("grad_fields");

    const undergradType = document.getElementById("undergrad_type");
    const gradType = document.getElementById("grad_type");
    const gradProgram = document.getElementById("grad_program");
    const thesis = document.getElementById("thesis");

    // Default setup
    undergradFields.style.display = "none";
    gradFields.style.display = "none";

    undergradType.removeAttribute("required");
    gradType.removeAttribute("required");
    gradProgram.removeAttribute("required");

    //Removes old values from the inputs incase you back track
    if (level !== "Undergraduate") {
        undergradType.value = "";
    }

    if (level !== "Graduate") {
        gradType.value = "";
        gradProgram.value = "";
        thesis.value = "";
    }

    // Enable deep conditional student verification sub-paths
    if (userType !== "Student") {
        return;
    }

    if (level === "Undergraduate") {
        undergradFields.style.display = "block";
        undergradType.setAttribute("required", "required");
    }

    else if (level === "Graduate") {
        gradFields.style.display = "block";
        gradType.setAttribute("required", "required");
        gradProgram.setAttribute("required", "required");
    }
}
// Ensure visibility checks run immediately when loading the environment
document.addEventListener("DOMContentLoaded", toggleFormSections);
