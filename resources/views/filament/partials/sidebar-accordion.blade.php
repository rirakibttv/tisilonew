<script data-navigate-once>
    (() => {
        const groupSelector = '.fi-main-sidebar .fi-sidebar-nav-groups > .fi-sidebar-group[data-group-label]';

        const getGroups = () => Array.from(document.querySelectorAll(groupSelector))
            .filter((group) => group.dataset.groupLabel?.trim());

        const updateCollapsedGroups = (sidebar, labels) => {
            sidebar.collapsedGroups = labels;

            try {
                localStorage.setItem('collapsedGroups', JSON.stringify(labels));
            } catch (error) {
                // Alpine's persisted store remains the source of truth when storage is unavailable.
            }
        };

        const syncActiveGroup = () => {
            const sidebar = window.Alpine?.store('sidebar');
            const groups = getGroups();

            if (! sidebar || groups.length === 0) {
                return false;
            }

            const activeLabel = groups.find((group) => group.classList.contains('fi-active'))
                ?.dataset.groupLabel;

            updateCollapsedGroups(
                sidebar,
                groups
                    .map((group) => group.dataset.groupLabel)
                    .filter((label) => label !== activeLabel),
            );

            return true;
        };

        const installAccordion = () => {
            const sidebar = window.Alpine?.store('sidebar');

            if (! sidebar) {
                return false;
            }

            if (! sidebar.__tisiloAccordion) {
                sidebar.toggleCollapsedGroup = function (label) {
                    const labels = getGroups().map((group) => group.dataset.groupLabel);
                    const collapsedGroups = Array.isArray(this.collapsedGroups)
                        ? this.collapsedGroups
                        : [];

                    updateCollapsedGroups(
                        this,
                        collapsedGroups.includes(label)
                            ? labels.filter((groupLabel) => groupLabel !== label)
                            : labels,
                    );
                };

                sidebar.__tisiloAccordion = true;
            }

            return syncActiveGroup();
        };

        const bootAccordion = (attempt = 0) => {
            if (installAccordion() || attempt >= 40) {
                return;
            }

            window.setTimeout(() => bootAccordion(attempt + 1), 50);
        };

        document.addEventListener('alpine:initialized', () => bootAccordion(), { once: true });
        document.addEventListener('livewire:navigated', () => {
            window.requestAnimationFrame(() => window.requestAnimationFrame(() => bootAccordion()));
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => bootAccordion(), { once: true });
        } else {
            bootAccordion();
        }
    })();
</script>
