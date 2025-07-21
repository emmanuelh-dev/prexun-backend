# Frontend Components - User Management with Suspension Field

Este directorio contiene componentes de React de ejemplo que implementan la funcionalidad del campo "suspendido" para usuarios.

## Archivos Incluidos

### 1. UserForm.jsx
Componente de formulario para crear y editar usuarios que incluye:
- Campos básicos: nombre, email, contraseña, rol
- **Checkbox para suspender/activar usuario**
- Selección múltiple de campus
- Validación de errores
- Manejo de estados de carga

### 2. UserList.jsx
Componente de lista que muestra todos los usuarios con:
- Tabla responsive con información de usuarios
- **Indicador visual del estado de suspensión**
- **Botón de toggle para suspender/activar usuarios**
- Modal para editar usuarios
- Funciones CRUD completas

## Características del Campo "Suspendido"

### Backend (Laravel)
1. **Migración**: Agrega campo `suspendido` tipo boolean con default `false`
2. **Modelo User**: Campo agregado a `$fillable` y `$casts`
3. **Controlador**: Validación y manejo en métodos `create` y `update`

### Frontend (React)
1. **Checkbox en formulario**: Permite marcar/desmarcar estado de suspensión
2. **Indicador visual**: Estados "Activo" (verde) y "Suspendido" (rojo)
3. **Toggle rápido**: Botón para cambiar estado sin abrir formulario
4. **Resaltado de filas**: Usuarios suspendidos tienen fondo rojo claro

## Uso de los Componentes

### Instalación de Dependencias
```bash
npm install axios react
# Si usas Tailwind CSS
npm install tailwindcss
```

### Importación
```jsx
import UserList from './UserList';
import UserForm from './UserForm';

// Usar el componente completo
function App() {
    return (
        <div className="App">
            <UserList />
        </div>
    );
}

// O usar solo el formulario
function CreateUser() {
    const handleSuccess = (user) => {
        console.log('Usuario creado:', user);
    };
    
    return (
        <UserForm onSuccess={handleSuccess} />
    );
}
```

## API Endpoints Utilizados

### GET /api/users
Obtiene lista de usuarios con relaciones de campus

### POST /api/users
Crea nuevo usuario con campos:
```json
{
    "name": "string",
    "email": "string",
    "password": "string",
    "role": "string",
    "suspendido": "boolean",
    "campuses": "array"
}
```

### PUT /api/users/{id}
Actualiza usuario existente (mismos campos que POST, password opcional)

### DELETE /api/users/{id}
Elimina usuario

## Mejoras Implementadas

### Código Limpio
- Componentes pequeños y enfocados
- Hooks personalizados para lógica reutilizable
- Manejo consistente de errores
- Estados de carga apropiados

### UX/UI
- Indicadores visuales claros para estado de suspensión
- Confirmaciones para acciones destructivas
- Formularios responsivos con validación
- Feedback inmediato en operaciones

### Performance
- Actualizaciones optimistas del estado local
- Mínimas re-renderizaciones
- Carga lazy de datos cuando es necesario

## Consideraciones de Seguridad

1. **Validación**: Tanto frontend como backend validan el campo `suspendido`
2. **Autorización**: Implementar middleware para verificar permisos
3. **Logs**: Registrar cambios de estado de suspensión para auditoría

## Próximos Pasos

1. **Integración con Moodle**: Sincronizar estado de suspensión con Moodle
2. **Notificaciones**: Enviar emails cuando un usuario es suspendido
3. **Historial**: Mantener log de cambios de estado
4. **Roles**: Restringir quién puede suspender usuarios

## Ejemplo de Uso Completo

```jsx
import React from 'react';
import UserList from './UserList';
import './App.css'; // Incluir Tailwind CSS

function App() {
    return (
        <div className="min-h-screen bg-gray-100">
            <header className="bg-white shadow">
                <div className="max-w-7xl mx-auto py-6 px-4">
                    <h1 className="text-3xl font-bold text-gray-900">
                        Sistema de Gestión de Usuarios
                    </h1>
                </div>
            </header>
            <main>
                <UserList />
            </main>
        </div>
    );
}

export default App;
```

Este ejemplo proporciona una base sólida para la gestión de usuarios con funcionalidad de suspensión, siguiendo las mejores prácticas de React y Laravel.