import { MACHINE_API_ENDPOINT } from './apiConfig';

export const getMachineStatus = async () => {
  const response = await fetch(`${MACHINE_API_ENDPOINT}/status`);
  if (!response.ok) {
    throw new Error(`Erreur API status: ${response.status} ${response.statusText}`);
  }
  const data = await response.json();
  return data.status || 'error'; // S'assurer qu'un statut est toujours retourné
};

export const controlMachine = async (action) => {
  const response = await fetch(`${MACHINE_API_ENDPOINT}/${action}`, {
    method: 'POST',
  });
  if (!response.ok) {
    const errorData = await response.text();
    throw new Error(`Erreur API machine (${action}): ${response.status} ${response.statusText} - ${errorData}`);
  }
  return response.status;
}; 