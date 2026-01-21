import { useMemo } from 'react';
import { useAdsManagerSelectionStore } from '@/stores/useCampaignSelectionStore';

export const useHierarchicalFilter = () => {
    const { selections } = useAdsManagerSelectionStore();
    
    const campaignSelections: Record<string, boolean> = selections['campaigns'] || {};
    const adSetSelections: Record<string, boolean> = selections['adSets'] || {};
    const adSelections: Record<string, boolean> = selections['ads'] || {};

    // Get selected IDs for each entity type
    const selectedCampaignIds = useMemo(() => {
        return Object.keys(campaignSelections).filter(id => campaignSelections[id]);
    }, [campaignSelections]);

    const selectedAdSetIds = useMemo(() => {
        return Object.keys(adSetSelections).filter(id => adSetSelections[id]);
    }, [adSetSelections]);

    const selectedAdIds = useMemo(() => {
        return Object.keys(adSelections).filter(id => adSelections[id]);
    }, [adSelections]);

    // Encode for URL params
    const encodedCampaignIds = useMemo(() => {
        return selectedCampaignIds.length > 0 
            ? encodeURIComponent(JSON.stringify(selectedCampaignIds)) 
            : undefined;
    }, [selectedCampaignIds]);

    const encodedAdSetIds = useMemo(() => {
        return selectedAdSetIds.length > 0 
            ? encodeURIComponent(JSON.stringify(selectedAdSetIds)) 
            : undefined;
    }, [selectedAdSetIds]);

    const encodedAdIds = useMemo(() => {
        return selectedAdIds.length > 0 
            ? encodeURIComponent(JSON.stringify(selectedAdIds)) 
            : undefined;
    }, [selectedAdIds]);

    return {
        selectedCampaignIds,
        selectedAdSetIds,
        selectedAdIds,
        encodedCampaignIds,
        encodedAdSetIds,
        encodedAdIds,
        hasCampaignFilter: selectedCampaignIds.length > 0,
        hasAdSetFilter: selectedAdSetIds.length > 0,
        hasAdFilter: selectedAdIds.length > 0,
        hasAnyFilter: selectedCampaignIds.length > 0 || selectedAdSetIds.length > 0 || selectedAdIds.length > 0,
    };
};
