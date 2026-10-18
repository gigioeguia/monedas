document.addEventListener('DOMContentLoaded', () => {
    const parentSelect = document.getElementById('category_id');
    const panelSelect = document.getElementById('category_panel');

    function updatePanelState() {
        const hasParent = parentSelect.value === '';
        panelSelect.disabled = !hasParent;

        if (!hasParent) {
            panelSelect.value = '';
        }
    }

    parentSelect.addEventListener('change', updatePanelState);
    updatePanelState();

    const input = document.getElementById('category_image');
    const fileName = document.getElementById('file-name');
    input.addEventListener('change', () => {
        fileName.textContent = input.files[0]?.name ?? 'Ningún archivo seleccionado';
    });
});