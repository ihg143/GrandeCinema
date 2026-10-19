document.addEventListener('DOMContentLoaded', function() {
    const salle = document.querySelector('.choix-salle');
    const conteneur = document.querySelector('.sieges');
    const boutonSimuler = document.querySelector('#bouton-simuler');

    const capacite = document.querySelector('#capacite');
    const spectateurs = document.querySelector('#spectateurs');
    const disponibles = document.querySelector('#disponibles');
    const taux = document.querySelector('#taux');
    const barre = document.querySelector('#barre');

    // true = place occupée, false = place libre
    let sieges = [];

    // Crée les places de la salle choisie
    function creerSieges() {
        const nombre = Number(salle.value);
        sieges = [];

        for (let i = 0; i < nombre; i++) {
            // environ 65% des places sont occupées au départ
            sieges.push(Math.random() < 0.65);
        }

        afficherSieges();
        majTableau();
    }

    // Affiche les boutons des places
    function afficherSieges() {
        conteneur.innerHTML = '';

        for (let i = 0; i < sieges.length; i++) {
            const bouton = document.createElement('button');
            bouton.textContent = i + 1;
            bouton.classList.add('siege');

            if (sieges[i] === true) {
                bouton.classList.add('occupe');
            } else {
                bouton.classList.add('libre');
            }

            // Clic : on change libre / occupée
            bouton.addEventListener('click', function() {
                sieges[i] = !sieges[i];
                bouton.classList.toggle('occupe');
                bouton.classList.toggle('libre');
                majTableau();
            });

            conteneur.appendChild(bouton);
        }
    }

    // Met à jour les 4 cartes du haut
    function majTableau() {
        let occupees = 0;

        for (let i = 0; i < sieges.length; i++) {
            if (sieges[i] === true) {
                occupees++;
            }
        }

        const total = sieges.length;
        const pourcentage = Math.round(occupees / total * 100);

        capacite.textContent = total;
        spectateurs.textContent = occupees;
        disponibles.textContent = total - occupees;
        taux.textContent = pourcentage + '%';
        barre.style.width = pourcentage + '%';
    }

    salle.addEventListener('change', creerSieges);
    boutonSimuler.addEventListener('click', creerSieges);

    creerSieges();
});
