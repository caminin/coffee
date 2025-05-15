import { GRAPHQL_ENDPOINT } from './apiConfig';

const fetchGraphQL = async (query, variables) => {
  const response = await fetch(GRAPHQL_ENDPOINT, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ query, variables }),
  });

  const responseText = await response.text();
  let result;
  try {
    result = JSON.parse(responseText);
  } catch (parseError) {
    console.error('Erreur parsing JSON GraphQL:', parseError, 'Texte:', responseText);
    throw new Error(`Réponse non-JSON (GraphQL status ${response.status}). Début: ${responseText.substring(0, 200)}`);
  }

  if (!response.ok || result.errors) {
    const errMsg = result.errors ? result.errors.map(err => err.message).join(', ') : `Erreur API GraphQL ${response.status}`;
    throw new Error(errMsg);
  }

  return result.data;
};

export const getInitialOrderHistory = async (limit = 10) => {
  const query = `
    query GetDernieresCommandesCafe($limit: Int) {
      dernieresCommandesCafe(limit: $limit) {
        id
        type
        intensite
        taille
        statut
        dateCreation
        dateDebutPreparation
        dateFinPreparation
      }
    }
  `;
  const data = await fetchGraphQL(query, { limit });
  return data.dernieresCommandesCafe || [];
};

export const createCoffeeOrder = async (order) => {
  const mutation = `
    mutation CommandeCafeCreate($type: String!, $intensite: Int!, $taille: String!) {
      commandeCafeCreate(type: $type, intensite: $intensite, taille: $taille) {
        id
        type
        intensite
        taille
        statut
        dateCreation
      }
    }
  `;
  const data = await fetchGraphQL(mutation, order);
  return data.commandeCafeCreate;
};

export const cancelPendingOrder = async (orderId) => {
  const mutation = `
    mutation CommandeCafeDelete($id: ID!) {
      commandeCafeDelete(id: $id) {
        id
      }
    }
  `;
  const data = await fetchGraphQL(mutation, { id: orderId });
  return data.commandeCafeDelete;
};

export const stopProcessingOrder = async (orderId) => {
  const mutation = `
    mutation AnnulerCommande($id: ID!) {
      annulerCommande(id: $id) {
        id
        # success
        # message
      }
    }
  `;
  const data = await fetchGraphQL(mutation, { id: orderId });
  return data.annulerCommande;
}; 