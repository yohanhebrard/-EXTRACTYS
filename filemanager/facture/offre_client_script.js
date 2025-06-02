// Ceci est le JavaScript côté client à inclure dans votre page HTML/PHP
// pour gérer la génération d'offre et le téléchargement du PDF

// Fonction pour générer l'offre en mode AJAX
function genererOffre(event) {
    // Empêcher le comportement par défaut du formulaire
    event.preventDefault();
    
    // Afficher un message de chargement
    document.getElementById('result').innerHTML = '<p>Génération de l\'offre en cours...</p>';
    
    // Récupérer les données du formulaire
    const formData = new FormData(document.getElementById('offreForm'));
    
    // Envoyer la requête AJAX (en mode AJAX)
    fetch('generer_offre_backend.php?ajax=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Réponse du serveur:', data);
        
        if (data.success) {
            // Succès - afficher un lien de téléchargement
            document.getElementById('result').innerHTML = `
                <p class="success-message">L'offre a été générée avec succès!</p>
                <p><a href="${data.download_url}" class="download-button" target="_blank">
                    <i class="fas fa-download"></i> Télécharger le PDF
                </a></p>
            `;
            
            // Télécharger automatiquement si demandé
            if (document.getElementById('telecharger_auto').checked) {
                window.location.href = data.download_url;
            }
        } else {
            // Erreur - afficher le message d'erreur
            document.getElementById('result').innerHTML = `
                <p class="error-message">Erreur: ${data.error || 'Une erreur inconnue est survenue'}</p>
            `;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        document.getElementById('result').innerHTML = `
            <p class="error-message">Erreur de communication avec le serveur: ${error.message}</p>
        `;
    });
}

// Attacher l'événement au formulaire après chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('offreForm');
    if (form) {
        form.addEventListener('submit', genererOffre);
    }
    
    // Initialiser les tooltips et autres éléments d'interface si nécessaire
    // ...
});
