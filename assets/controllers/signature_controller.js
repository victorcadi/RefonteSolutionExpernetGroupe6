import { Controller } from '@hotwired/stimulus';

/*
 * Signature de l'émargement.
 *
 * Sans JavaScript, la page ne propose que la saisie du nom. Ce contrôleur ajoute
 * le choix du mode et le tracé au doigt, au stylet ou à la souris dans un canvas ;
 * à l'envoi, le tracé est copié dans le champ caché sous forme d'image PNG.
 */
export default class extends Controller {
    static targets = ['choix', 'panneauTrace', 'panneauSaisie', 'canvas', 'champ', 'etat'];

    connect() {
        this.choixTarget.hidden = false;
        this.panneauTraceTarget.hidden = false;
        this.dessine = false;
        this.enCours = false;

        // Par défaut, le tracé est proposé (sauf si la personne a déjà choisi la saisie).
        if (!this.radioCochee()) {
            this.radio('manuscrite').checked = true;
        }
        this.afficherMode();

        this.contexte = this.canvasTarget.getContext('2d');
        this.dimensionner();
        this.redimensionnement = () => { if (!this.dessine) this.dimensionner(); };
        window.addEventListener('resize', this.redimensionnement);
    }

    disconnect() {
        window.removeEventListener('resize', this.redimensionnement);
    }

    changerMode() {
        this.afficherMode();
    }

    commencer(evenement) {
        evenement.preventDefault();
        this.canvasTarget.setPointerCapture(evenement.pointerId);
        this.enCours = true;
        const point = this.position(evenement);
        this.contexte.beginPath();
        this.contexte.moveTo(point.x, point.y);
        // Un simple appui laisse un point visible.
        this.contexte.lineTo(point.x + 0.1, point.y + 0.1);
        this.contexte.stroke();
    }

    tracer(evenement) {
        if (!this.enCours) return;
        evenement.preventDefault();
        const point = this.position(evenement);
        this.contexte.lineTo(point.x, point.y);
        this.contexte.stroke();
        if (!this.dessine) {
            this.dessine = true;
            this.etatTarget.textContent = 'Signature tracée.';
        }
    }

    terminer() {
        this.enCours = false;
    }

    effacer() {
        this.contexte.clearRect(0, 0, this.canvasTarget.width, this.canvasTarget.height);
        this.dessine = false;
        this.champTarget.value = '';
        this.etatTarget.textContent = 'Signature effacée.';
    }

    envoyer() {
        this.champTarget.value = this.radio('manuscrite').checked && this.dessine ? this.exporter() : '';
    }

    // --- Outils -------------------------------------------------------------

    afficherMode() {
        const trace = this.radio('manuscrite').checked;
        this.panneauTraceTarget.hidden = !trace;
        this.panneauSaisieTarget.hidden = trace;
    }

    radio(valeur) {
        return this.choixTarget.querySelector(`input[type="radio"][value="${valeur}"]`);
    }

    radioCochee() {
        return this.choixTarget.querySelector('input[type="radio"]:checked');
    }

    /** Taille réelle du canvas = taille affichée × densité de l'écran (tracé net). */
    dimensionner() {
        const ratio = window.devicePixelRatio || 1;
        const { width, height } = this.canvasTarget.getBoundingClientRect();
        if (width === 0) return;
        this.canvasTarget.width = Math.round(width * ratio);
        this.canvasTarget.height = Math.round(height * ratio);
        this.contexte.setTransform(ratio, 0, 0, ratio, 0, 0);
        this.contexte.lineWidth = 2.5;
        this.contexte.lineCap = 'round';
        this.contexte.lineJoin = 'round';
        this.contexte.strokeStyle = '#0F3A4B';
    }

    position(evenement) {
        const cadre = this.canvasTarget.getBoundingClientRect();
        return { x: evenement.clientX - cadre.left, y: evenement.clientY - cadre.top };
    }

    /** Image ramenée à la taille affichée : poids et dimensions stables quel que soit l'écran. */
    exporter() {
        const { width, height } = this.canvasTarget.getBoundingClientRect();
        const copie = document.createElement('canvas');
        copie.width = Math.round(width);
        copie.height = Math.round(height);
        copie.getContext('2d').drawImage(this.canvasTarget, 0, 0, copie.width, copie.height);
        return copie.toDataURL('image/png');
    }
}
