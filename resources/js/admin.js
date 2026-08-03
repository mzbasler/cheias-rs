/**
 * Visualizador de foto: qualquer botão com data-photo-trigger abre a imagem
 * grande no <dialog> compartilhado — mesmo padrão de modal do mapa público,
 * sem precisar de biblioteca de lightbox pra uma imagem só por vez.
 */
const viewer = document.getElementById('photo-viewer');

if (viewer) {
    const image = viewer.querySelector('img');

    document.querySelectorAll('[data-photo-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            image.src = trigger.dataset.photoTrigger;
            viewer.showModal();
        });
    });

    // Clique no fundo escurecido fecha — clique na própria imagem não chega
    // aqui, o <dialog> já para a propagação antes.
    viewer.addEventListener('click', (event) => {
        if (event.target === viewer) {
            viewer.close();
        }
    });
}
