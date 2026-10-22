/* Scroll Reveal */
function revealOnScroll(){
    let reveals = document.querySelectorAll(".reveal");

    reveals.forEach((el)=>{
        let windowHeight = window.innerHeight;
        let elementTop = el.getBoundingClientRect().top;

        if(elementTop < windowHeight - 100){
            el.classList.add("active");
        }
    });
}

window.addEventListener("scroll", revealOnScroll);

/* Animated Counter */
function animateCounters(){
    document.querySelectorAll('.stat-card p').forEach(counter=>{
        let target = +counter.innerText.replace(/[^0-9]/g,'');
        let count = 0;
        let increment = target / 60;

        function update(){
            count += increment;
            if(count < target){
                counter.innerText = Math.floor(count);
                requestAnimationFrame(update);
            } else {
                counter.innerText = target;
            }
        }

        update();
    });
}

window.onload = animateCounters;
